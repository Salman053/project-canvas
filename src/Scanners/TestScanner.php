<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Illuminate\Support\Facades\File;
use Salman053\Canvas\Data\Edge;

class TestScanner
{
    private array $allNodes = [];

    public function setAllNodes(array $nodes): void
    {
        $this->allNodes = $nodes;
    }

    public function scan(): array
    {
        $edges = [];

        $testPaths = [
            base_path('tests'),
        ];

        foreach ($testPaths as $path) {
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

                $useStatements = $this->extractUseStatements($contents);
                $testName = $file->getFilenameWithoutExtension();

                foreach ($useStatements as $use) {
                    foreach ($this->allNodes as $node) {
                        if ($this->classMatches($use, $node->getNamespace())) {
                            $edgeId = 'edge_test_'.str_replace('\\', '_', $testName.'_'.$node->getId());

                            if (! isset($edges[$edgeId])) {
                                $edge = new Edge(
                                    id: $edgeId,
                                    sourceId: $node->getId(),
                                    targetId: 'test_'.str_replace(['\\', '.'], '_', $testName),
                                    type: Edge::TYPE_TEST,
                                    label: $testName,
                                );

                                $edge->setMetadata('testFile', $file->getPathname());

                                $edges[$edgeId] = $edge;

                                $node->addTestResult($testName, true);

                                break;
                            }
                        }
                    }
                }
            }
        }

        return $edges;
    }

    private function extractUseStatements(string $contents): array
    {
        $uses = [];

        preg_match_all('/^use\s+([^;]+);/m', $contents, $matches);

        foreach ($matches[1] as $use) {
            $uses[] = trim($use);
        }

        return $uses;
    }

    private function classMatches(string $useStatement, string $fullClass): bool
    {
        return $useStatement === $fullClass;
    }
}
