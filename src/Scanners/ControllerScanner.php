<?php

declare(strict_types=1);

namespace VendorName\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use VendorName\Canvas\Data\Node;

class ControllerScanner
{
    public function scan(): array
    {
        $controllers = [];
        $paths = [
            app_path('Http/Controllers'),
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

                preg_match('/namespace\s+([^;]+);/', $contents, $ns);
                preg_match('/class\s+(\w+)/', $contents, $cls);

                $className = $cls[1] ?? $file->getFilenameWithoutExtension();
                $namespace = $ns[1] ?? 'App';
                $fullClass = $namespace.'\\'.$className;
                $nodeId = 'controller_'.str_replace('\\', '_', $fullClass);

                $node = new Node(
                    id: $nodeId,
                    label: $className,
                    type: Node::TYPE_CONTROLLER,
                    namespace: $fullClass,
                    filePath: $file->getPathname(),
                );

                $node->setMetadata('methods', $this->extractMethods($contents));
                $node->setMetadata('middleware', $this->extractMiddleware($contents));
                $node->setMetadata('injections', $this->extractConstructorInjections($contents));

                $controllers[$nodeId] = $node;
            }
        }

        return $controllers;
    }

    private function extractMethods(string $contents): array
    {
        $methods = [];

        preg_match_all(
            '/(public\s+)?function\s+(\w+)\s*\(([^)]*)\)\s*(?::\s*\??\s*(\w+))?\s*\{/',
            $contents,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $name = $match[2];

            if (in_array($name, ['__construct', '__invoke', '__call', '__callStatic'])) {
                continue;
            }

            $methods[] = [
                'name' => $name,
                'params' => array_map('trim', explode(',', $match[3])),
                'returnType' => $match[4] ?? null,
            ];
        }

        return $methods;
    }

    private function extractMiddleware(string $contents): array
    {
        $middleware = [];

        preg_match_all(
            '/\$this->middleware\([\'"]([^\'"]+)[\'"]/',
            $contents,
            $matches,
        );

        foreach ($matches[1] as $mw) {
            $middleware[] = $mw;
        }

        return $middleware;
    }

    private function extractConstructorInjections(string $contents): array
    {
        $injections = [];

        if (preg_match('/function\s+__construct\s*\(([^)]*)\)/', $contents, $m)) {
            $params = explode(',', $m[1]);

            foreach ($params as $param) {
                $param = trim($param);

                if (preg_match('/(\w+)\s+\$(\w+)/', $param, $pm)) {
                    $injections[] = [
                        'type' => $pm[1],
                        'variable' => $pm[2],
                    ];
                }
            }
        }

        return $injections;
    }
}
