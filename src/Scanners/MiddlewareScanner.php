<?php

declare(strict_types=1);

namespace VendorName\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use VendorName\Canvas\Data\Node;

class MiddlewareScanner
{
    public function scan(): array
    {
        $middleware = [];
        $paths = [
            app_path('Http/Middleware'),
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

                if (! preg_match('/class\s+(\w+)/', $contents, $cls)) {
                    continue;
                }

                preg_match('/namespace\s+([^;]+);/', $contents, $ns);

                $className = $cls[1];
                $namespace = $ns[1] ?? 'App';
                $fullClass = $namespace.'\\'.$className;
                $nodeId = 'middleware_'.str_replace('\\', '_', $fullClass);

                $node = new Node(
                    id: $nodeId,
                    label: $className,
                    type: Node::TYPE_MIDDLEWARE,
                    namespace: $fullClass,
                    filePath: $file->getPathname(),
                );

                $node->setMetadata('hasHandle', str_contains($contents, 'function handle('));
                $node->setMetadata('aliases', $this->extractAliases($contents));

                $middleware[$nodeId] = $node;
            }
        }

        return $middleware;
    }

    private function extractAliases(string $contents): array
    {
        $aliases = [];

        if (preg_match('/protected\s+\$aliases\s*=\s*\[([^\]]+)\]/s', $contents, $m)) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $m[1], $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $aliases[$match[1]] = $match[2];
            }
        }

        return $aliases;
    }
}
