<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use Salman053\Canvas\Data\Node;

class JobScanner
{
    public function scan(): array
    {
        $jobs = [];
        $paths = [
            app_path('Jobs'),
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

                if (! preg_match('/class\s+\w+.*implements.*ShouldQueue/', $contents)) {
                    continue;
                }

                preg_match('/namespace\s+([^;]+);/', $contents, $ns);
                preg_match('/class\s+(\w+)/', $contents, $cls);

                $className = $cls[1] ?? $file->getFilenameWithoutExtension();
                $namespace = $ns[1] ?? 'App';
                $fullClass = $namespace.'\\'.$className;
                $nodeId = 'job_'.str_replace('\\', '_', $fullClass);

                $node = new Node(
                    id: $nodeId,
                    label: $className,
                    type: Node::TYPE_JOB,
                    namespace: $fullClass,
                    filePath: $file->getPathname(),
                );

                $node->setMetadata('methods', $this->extractMethods($contents));
                $node->setMetadata('queue', $this->extractQueue($contents));
                $node->setMetadata('maxAttempts', $this->extractTries($contents));
                $node->setMetadata('properties', $this->extractProperties($contents));

                $jobs[$nodeId] = $node;
            }
        }

        return $jobs;
    }

    private function extractQueue(string $contents): ?string
    {
        if (preg_match('/public\s+\$queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractTries(string $contents): int
    {
        if (preg_match('/public\s+\$tries\s*=\s*(\d+)/', $contents, $m)) {
            return (int) $m[1];
        }

        return 1;
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

    private function extractProperties(string $contents): array
    {
        $properties = [];

        preg_match_all('/public\s+(?:\w+\s+)?\$(\w+)/', $contents, $matches);

        foreach ($matches[1] as $prop) {
            if (! in_array($prop, ['tries', 'queue', 'timeout', 'maxExceptions', 'backoff'])) {
                $properties[] = $prop;
            }
        }

        return $properties;
    }
}
