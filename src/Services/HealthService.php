<?php

declare(strict_types=1);

namespace VendorName\Canvas\Services;

use VendorName\Canvas\Data\ArchitectureGraph;
use VendorName\Canvas\Data\Node;

class HealthService
{
    private ComplexityAnalyzer $complexityAnalyzer;

    public function __construct()
    {
        $this->complexityAnalyzer = new ComplexityAnalyzer;
    }

    public function calculateNodeHealth(Node $node): float
    {
        $complexity = $this->complexityAnalyzer->getCyclomaticComplexity($node);
        $node->setComplexityScore($complexity);

        $testPassRate = $node->getTestPassRate();
        $depCount = count($node->getDependencies());
        $dependentCount = count($node->getDependents());

        $testScore = $testPassRate;
        $depScore = max(0, 1 - ($depCount * 0.05));
        $complexityScore = max(0, 1 - ($complexity * 0.02));
        $responsibilityScore = min(1, ($dependentCount + 1) * 0.1);

        $health = ($testScore * 0.35)
            + ($depScore * 0.20)
            + ($complexityScore * 0.25)
            + ($responsibilityScore * 0.20);

        return max(0.0, min(1.0, $health));
    }

    public function calculateGraphHealth(ArchitectureGraph $graph): array
    {
        $nodes = $graph->getNodes();

        if (empty($nodes)) {
            return [
                'averageHealth' => 0,
                'healthyCount' => 0,
                'moderateCount' => 0,
                'unhealthyCount' => 0,
                'totalNodes' => 0,
            ];
        }

        $healthy = 0;
        $moderate = 0;
        $unhealthy = 0;
        $totalHealth = 0;

        foreach ($nodes as $node) {
            $health = $node->getHealthScore();
            $totalHealth += $health;

            match (true) {
                $health >= 0.8 => $healthy++,
                $health >= 0.5 => $moderate++,
                default => $unhealthy++,
            };
        }

        return [
            'averageHealth' => round($totalHealth / count($nodes), 2),
            'healthyCount' => $healthy,
            'moderateCount' => $moderate,
            'unhealthyCount' => $unhealthy,
            'totalNodes' => count($nodes),
        ];
    }

    public function identifyGodClasses(ArchitectureGraph $graph): array
    {
        $godClasses = [];

        foreach ($graph->getNodes() as $node) {
            $complexity = $this->complexityAnalyzer->getCyclomaticComplexity($node);
            $methodCount = count($node->getMetadata('methods', []));
            $dependencyCount = count($node->getDependencies());

            $godScore = ($complexity * 0.4) + ($methodCount * 0.3) + ($dependencyCount * 0.3);

            if ($godScore > 15) {
                $godClasses[] = [
                    'node' => $node->toArray(),
                    'godScore' => round($godScore, 1),
                    'complexity' => $complexity,
                    'methodCount' => $methodCount,
                    'dependencyCount' => $dependencyCount,
                ];
            }
        }

        usort($godClasses, fn ($a, $b) => $b['godScore'] <=> $a['godScore']);

        return $godClasses;
    }
}
