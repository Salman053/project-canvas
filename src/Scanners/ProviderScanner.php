<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use Salman053\Canvas\Data\Node;

class ProviderScanner
{
    public function scan(): array
    {
        $providers = [];
        $paths = [
            app_path('Providers'),
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

                if (! preg_match('/class\s+(\w+).*extends.*ServiceProvider/', $contents)) {
                    continue;
                }

                preg_match('/namespace\s+([^;]+);/', $contents, $ns);
                preg_match('/class\s+(\w+)/', $contents, $cls);

                $className = $cls[1];
                $namespace = $ns[1] ?? 'App';
                $fullClass = $namespace.'\\'.$className;
                $nodeId = 'provider_'.str_replace('\\', '_', $fullClass);

                $node = new Node(
                    id: $nodeId,
                    label: $className,
                    type: Node::TYPE_PROVIDER,
                    namespace: $fullClass,
                    filePath: $file->getPathname(),
                );

                $node->setMetadata('bindings', $this->extractBindings($contents));
                $node->setMetadata('hasRegister', str_contains($contents, 'function register('));
                $node->setMetadata('hasBoot', str_contains($contents, 'function boot('));

                $providers[$nodeId] = $node;
            }
        }

        return $providers;
    }

    private function extractBindings(string $contents): array
    {
        $bindings = [];

        preg_match_all(
            '/\$this->(?:app->)?(?:bind|singleton)\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            $contents,
            $matches,
        );

        foreach ($matches[1] as $binding) {
            $bindings[] = $binding;
        }

        return $bindings;
    }
}
