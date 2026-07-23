<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use Salman053\Canvas\Data\Node;

class PolicyScanner
{
    public function scan(): array
    {
        $policies = [];
        $paths = [
            app_path('Policies'),
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
                $nodeId = 'policy_'.str_replace('\\', '_', $fullClass);

                $node = new Node(
                    id: $nodeId,
                    label: $className,
                    type: Node::TYPE_POLICY,
                    namespace: $fullClass,
                    filePath: $file->getPathname(),
                );

                $node->setMetadata('methods', $this->extractMethods($contents));

                $policies[$nodeId] = $node;
            }
        }

        return $policies;
    }

    private function extractMethods(string $contents): array
    {
        $methods = [];

        preg_match_all(
            '/function\s+(viewAny|view|create|update|delete|restore|forceDelete)\s*\(/',
            $contents,
            $matches,
        );

        return $matches[1];
    }
}
