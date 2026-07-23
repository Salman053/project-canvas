<?php

declare(strict_types=1);

namespace VendorName\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use VendorName\Canvas\Data\Node;

class ModelScanner
{
    public function scan(): array
    {
        $models = [];
        $paths = [
            app_path('Models'),
            app_path(),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (! $contents) {
                    continue;
                }

                if (! preg_match('/class\s+(\w+).*extends\s+.*(?:Model|Pivot|Authenticatable)/', $contents)) {
                    continue;
                }

                preg_match('/namespace\s+([^;]+);/', $contents, $ns);
                preg_match('/class\s+(\w+)/', $contents, $cls);

                $className = $cls[1] ?? $file->getFilenameWithoutExtension();
                $namespace = $ns[1] ?? 'App';
                $fullClass = $namespace.'\\'.$className;
                $nodeId = 'model_'.str_replace('\\', '_', $fullClass);

                $node = new Node(
                    id: $nodeId,
                    label: $className,
                    type: Node::TYPE_MODEL,
                    namespace: $fullClass,
                    filePath: $file->getPathname(),
                );

                $node->setMetadata('table', $this->guessTableName($className, $contents));
                $node->setMetadata('relationships', $this->extractRelationships($contents));
                $node->setMetadata('casts', $this->extractCasts($contents));
                $node->setMetadata('fillable', $this->extractFillable($contents));

                $models[$nodeId] = $node;
            }
        }

        return $models;
    }

    private function guessTableName(string $className, string $contents): string
    {
        if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }

        return str_plural(Str::snake($className));
    }

    private function extractRelationships(string $contents): array
    {
        $relationships = [];
        $patterns = [
            '/function\s+(\w+)\s*\(\s*\)\s*\s*:\s*[^;{]*\n?\s*\{[^}]*\b(hasMany|belongsTo|belongsToMany|hasOne|morphMany|morphToMany|morphTo|hasManyThrough)\s*\(/s',
            '/function\s+(\w+)\s*\(\s*\)\s*\s*\n?\s*\{[^}]*\breturn\s+\$this->(hasMany|belongsTo|belongsToMany|hasOne|morphMany|morphToMany|morphTo|hasManyThrough)\s*\(/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $relationships[] = [
                        'method' => $match[1],
                        'type' => $match[2],
                    ];
                }
            }
        }

        return $relationships;
    }

    private function extractCasts(string $contents): array
    {
        $casts = [];

        if (preg_match('/protected\s+\$casts\s*=\s*\[([^\]]+)\]/s', $contents, $m)) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $m[1], $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $casts[$match[1]] = $match[2];
            }
        }

        return $casts;
    }

    private function extractFillable(string $contents): array
    {
        $fillable = [];

        if (preg_match('/protected\s+\$fillable\s*=\s*\[([^\]]+)\]/s', $contents, $m)) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $matches);

            foreach ($matches[1] as $field) {
                $fillable[] = $field;
            }
        }

        return $fillable;
    }
}
