<?php

declare(strict_types=1);

namespace VendorName\Canvas\Scanners;

use VendorName\Canvas\Data\Edge;

class DependencyScanner
{
    private array $allNodes = [];

    public function setAllNodes(array $nodes): void
    {
        $this->allNodes = $nodes;
    }

    public function scan(): array
    {
        $edges = [];

        foreach ($this->allNodes as $node) {
            $filePath = $node->getFilePath();

            if (! $filePath || ! file_exists($filePath)) {
                continue;
            }

            $contents = file_get_contents($filePath);

            if (! $contents) {
                continue;
            }

            $useStatements = $this->extractUseStatements($contents);

            foreach ($useStatements as $use) {
                foreach ($this->allNodes as $targetNode) {
                    if ($targetNode->getId() === $node->getId()) {
                        continue;
                    }

                    if ($this->classMatches($use, $targetNode->getNamespace())) {
                        $edgeId = 'edge_dep_'.str_replace('\\', '_', $node->getId().'_'.$targetNode->getId());

                        if (! isset($edges[$edgeId])) {
                            $edge = new Edge(
                                id: $edgeId,
                                sourceId: $node->getId(),
                                targetId: $targetNode->getId(),
                                type: Edge::TYPE_DEPENDENCY,
                                label: 'uses',
                            );

                            $edges[$edgeId] = $edge;
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
        return $useStatement === $fullClass
            || str_ends_with($useStatement, '\\'.(explode('\\', $fullClass)[0] ?? ''));
    }
}
