<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use Salman053\Canvas\Data\Node;

class ListenerScanner
{
    public function scan(): array
    {
        $listeners = [];
        $paths = [
            app_path('Listeners'),
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
                $nodeId = 'listener_'.str_replace('\\', '_', $fullClass);

                $node = new Node(
                    id: $nodeId,
                    label: $className,
                    type: Node::TYPE_LISTENER,
                    namespace: $fullClass,
                    filePath: $file->getPathname(),
                );

                $node->setMetadata('methods', $this->extractMethods($contents));
                $node->setMetadata('handles', $this->extractHandledEvents($contents));
                $node->setMetadata('queued', str_contains($contents, 'ShouldQueue'));

                $listeners[$nodeId] = $node;
            }
        }

        return $listeners;
    }

    private function extractHandledEvents(string $contents): array
    {
        $events = [];

        preg_match_all(
            '/function\s+handle\s*\(\s*([^:)]+)\s+\$\w+/',
            $contents,
            $matches,
        );

        foreach ($matches[1] as $event) {
            $event = trim($event);

            if ($event && ! str_starts_with($event, '$')) {
                $events[] = $event;
            }
        }

        return $events;
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
