<?php

declare(strict_types=1);

namespace VendorName\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use VendorName\Canvas\Data\Node;

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
}
