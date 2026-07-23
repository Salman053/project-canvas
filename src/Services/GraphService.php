<?php

declare(strict_types=1);

namespace Salman053\Canvas\Services;

use Salman053\Canvas\Data\ArchitectureGraph;
use Salman053\Canvas\Data\Node;

class GraphService
{
    private HealthService $healthService;

    private ComplexityAnalyzer $complexityAnalyzer;

    public function __construct()
    {
        $this->healthService = new HealthService;
        $this->complexityAnalyzer = new ComplexityAnalyzer;
    }

    public function enrichGraph(ArchitectureGraph $graph): ArchitectureGraph
    {
        foreach ($graph->getNodes() as $node) {
            $complexity = $this->complexityAnalyzer->analyze($node);
            $node->setComplexityScore($complexity);

            $health = $this->healthService->calculateNodeHealth($node);
            $node->setHealthScore($health);
        }

        return $graph;
    }

    public function getNodeById(ArchitectureGraph $graph, string $id): ?Node
    {
        return $graph->getNode($id);
    }

    public function searchNodes(ArchitectureGraph $graph, string $query): array
    {
        $query = strtolower($query);

        return array_values(
            array_filter(
                $graph->getNodes(),
                fn (Node $node) => str_contains(strtolower($node->getLabel()), $query)
                    || str_contains(strtolower($node->getNamespace()), $query),
            ),
        );
    }

    public function filterByType(ArchitectureGraph $graph, string $type): array
    {
        return $graph->getNodesByType($type);
    }

    public function getNodeDependencyGraph(ArchitectureGraph $graph, string $nodeId): array
    {
        $node = $graph->getNode($nodeId);

        if (! $node) {
            return ['incoming' => [], 'outgoing' => []];
        }

        $incoming = [];
        foreach ($node->getDependents() as $depId) {
            $depNode = $graph->getNode($depId);

            if ($depNode) {
                $incoming[] = $depNode->toArray();
            }
        }

        $outgoing = [];
        foreach ($node->getDependencies() as $depId) {
            $depNode = $graph->getNode($depId);

            if ($depNode) {
                $outgoing[] = $depNode->toArray();
            }
        }

        return ['incoming' => $incoming, 'outgoing' => $outgoing];
    }

    public function getDashboardStats(ArchitectureGraph $graph): array
    {
        $nodeTypeCounts = [];

        foreach ($graph->getNodes() as $node) {
            $type = $node->getType();
            $nodeTypeCounts[$type] = ($nodeTypeCounts[$type] ?? 0) + 1;
        }

        $healthSummary = $this->healthService->calculateGraphHealth($graph);
        $godClasses = $this->healthService->identifyGodClasses($graph);

        return [
            'totalNodes' => $graph->getNodeCount(),
            'totalEdges' => $graph->getEdgeCount(),
            'averageDependencies' => round($graph->getAverageDependencies(), 2),
            'averageHealth' => $healthSummary['averageHealth'],
            'nodeTypeCounts' => $nodeTypeCounts,
            'healthSummary' => $healthSummary,
            'godClasses' => $godClasses,
            'snapshotCount' => count($graph->getSnapshots()),
        ];
    }
}
