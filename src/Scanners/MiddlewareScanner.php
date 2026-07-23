<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use Salman053\Canvas\Data\Node;

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

                $node->setMetadata('methods', $this->extractMethods($contents));
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

    private function extractMethods(string $contents): array
    {
        $methods = [];

        preg_match_all('/(public\s+)?function\s+(\w+)\s*\(([^)]*)\)\s*(?::\s*\??\s*(\w+))?\s*\{/', $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (in_array($match[2], ['__construct', '__invoke', '__call', '__callStatic'])) {
                continue;
            }

            $methods[] = ['name' => $match[2], 'params' => array_map('trim', explode(',', $match[3])), 'returnType' => $match[4] ?? null];
        }

        return $methods;
    }
}
