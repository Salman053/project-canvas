<?php

declare(strict_types=1);

namespace Salman053\Canvas\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Salman053\Canvas\Data\ArchitectureGraph;
use Salman053\Canvas\Data\Edge;
use Salman053\Canvas\Data\Node;
use Salman053\Canvas\Scanners\CodebaseScanner;
use Salman053\Canvas\Scanners\GitAnalyzer;
use Salman053\Canvas\Services\GraphService;
use Salman053\Canvas\Services\HealthService;

class CanvasApiController extends Controller
{
    private ArchitectureGraph $graph;

    private GraphService $graphService;

    private HealthService $healthService;

    private CodebaseScanner $scanner;

    public function __construct()
    {
        $this->graphService = new GraphService;
        $this->healthService = new HealthService;
        $this->scanner = new CodebaseScanner;
        $this->graph = new ArchitectureGraph;
    }

    private function loadGraph(): ArchitectureGraph
    {
        $cachedPath = storage_path('app/canvas-graph.json');

        if (file_exists($cachedPath)) {
            $data = json_decode(file_get_contents($cachedPath), true);

            if ($data && isset($data['nodes'])) {
                return $this->hydrateGraph($data);
            }
        }

        $graph = $this->scanner->scan();
        $graph = $this->graphService->enrichGraph($graph);

        file_put_contents($cachedPath, json_encode($graph->toArray(), JSON_PRETTY_PRINT));

        return $graph;
    }

    public function graph(): JsonResponse
    {
        $graph = $this->loadGraph();

        return response()->json($graph->toArray());
    }

    public function node(string $id): JsonResponse
    {
        $graph = $this->loadGraph();
        $node = $graph->getNode($id);

        if (! $node) {
            return response()->json(['error' => 'Node not found'], 404);
        }

        $deps = $this->graphService->getNodeDependencyGraph($graph, $id);

        return response()->json([
            'node' => $node->toArray(),
            'sourceCode' => $node->getSourceCode(),
            'dependencies' => $deps,
            'edges' => array_map(
                fn (Edge $e) => $e->toArray(),
                $graph->getEdgesForNode($id),
            ),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1']);

        $graph = $this->loadGraph();
        $results = $this->graphService->searchNodes($graph, $request->input('q'));

        return response()->json([
            'results' => array_map(fn (Node $n) => $n->toArray(), $results),
            'total' => count($results),
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $graph = $this->loadGraph();

        return response()->json(
            $this->graphService->getDashboardStats($graph),
        );
    }

    public function health(): JsonResponse
    {
        $graph = $this->loadGraph();

        return response()->json([
            'graphHealth' => $this->healthService->calculateGraphHealth($graph),
            'godClasses' => $this->healthService->identifyGodClasses($graph),
        ]);
    }

    public function filter(string $type): JsonResponse
    {
        $graph = $this->loadGraph();
        $filtered = $graph->getNodesByType($type);

        return response()->json([
            'type' => $type,
            'nodes' => array_map(fn (Node $n) => $n->toArray(), $filtered),
            'total' => count($filtered),
        ]);
    }

    public function heatmap(): JsonResponse
    {
        $gitAnalyzer = new GitAnalyzer;

        return response()->json([
            'commitHeatmap' => $gitAnalyzer->getCommitHeatmap(),
            'recentCommits' => $gitAnalyzer->getRecentCommitCount(),
            'timeline' => $gitAnalyzer->getTimelineSnapshots(),
        ]);
    }

    public function snapshot(): JsonResponse
    {
        $graph = $this->loadGraph();
        $snapshotId = $graph->takeSnapshot('api-snapshot-'.now()->format('YmdHis'));

        return response()->json([
            'snapshotId' => $snapshotId,
            'graph' => $graph->toArray(),
        ]);
    }

    public function snapshotList(): JsonResponse
    {
        $graph = $this->loadGraph();

        return response()->json([
            'snapshots' => $graph->getSnapshots(),
        ]);
    }

    public function export(): JsonResponse
    {
        $graph = $this->loadGraph();

        return response()->json([
            'exportedAt' => now()->toIso8601String(),
            'appName' => config('app.name'),
            'graph' => $graph->toArray(),
            'dashboard' => $this->graphService->getDashboardStats($graph),
        ]);
    }

    public function analytics(): JsonResponse
    {
        $graph = $this->loadGraph();
        $stats = $this->graphService->getDashboardStats($graph);
        $health = $this->healthService->calculateGraphHealth($graph);
        $godClasses = $this->healthService->identifyGodClasses($graph);
        $nodes = $graph->getNodes();
        $edges = $graph->getEdges();

        $complexityByType = [];
        $healthByType = [];
        foreach ($nodes as $node) {
            $type = $node->getType();
            $complexityByType[$type][] = $node->getComplexityScore();
            $healthByType[$type][] = $node->getHealthScore();
        }

        $avgComplexityByType = [];
        $avgHealthByType = [];
        foreach ($complexityByType as $type => $scores) {
            $avgComplexityByType[$type] = round(array_sum($scores) / count($scores), 2);
            $avgHealthByType[$type] = round(array_sum($healthByType[$type]) / count($healthByType[$type]), 2);
        }

        $routeNodes = $graph->getNodesByType('route');
        $highDepNodes = array_filter($nodes, fn (Node $n) => count($n->getDependencies()) > 3);

        /** @var array{node: array<string, mixed>, complexity: int, methodCount: int, dependencyCount: int} $gc */
        $suggestions = [];
        foreach ($godClasses as $gc) {
            $suggestions[] = [
                'type' => 'warning',
                'icon' => 'god-class',
                'title' => 'God Class Detected',
                'message' => $gc['node']['label'].' has high complexity ('.$gc['complexity'].') with '.$gc['methodCount'].' methods and '.$gc['dependencyCount'].' dependencies. Consider splitting into smaller services.',
                'component' => $gc['node']['label'],
                'severity' => 'high',
            ];
        }

        $unhealthy = array_filter($nodes, fn (Node $n) => $n->getHealthScore() < 0.5);
        foreach ($unhealthy as $node) {
            $suggestions[] = [
                'type' => 'danger',
                'icon' => 'health',
                'title' => 'Low Health Score',
                'message' => "{$node->getLabel()} has a health score of ".round($node->getHealthScore() * 100).'%. Review code quality, add tests, and reduce dependencies.',
                'component' => $node->getLabel(),
                'severity' => 'high',
            ];
        }

        $highComplexity = array_filter($nodes, fn (Node $n) => $n->getComplexityScore() > 8);
        foreach ($highComplexity as $node) {
            $suggestions[] = [
                'type' => 'warning',
                'icon' => 'complexity',
                'title' => 'High Complexity',
                'message' => "{$node->getLabel()} has complexity {$node->getComplexityScore()}. Break down complex methods and reduce nesting.",
                'component' => $node->getLabel(),
                'severity' => 'medium',
            ];
        }

        $models = $graph->getNodesByType('model');
        foreach ($models as $model) {
            $rels = $model->getMetadata('relationships', []);
            if (count($rels) > 5) {
                $suggestions[] = [
                    'type' => 'info',
                    'icon' => 'model',
                    'title' => 'Complex Model',
                    'message' => "{$model->getLabel()} has ".count($rels).' relationships. Consider using Single Table Inheritance or splitting into related models.',
                    'component' => $model->getLabel(),
                    'severity' => 'low',
                ];
            }
        }

        $controllerNodes = $graph->getNodesByType('controller');
        foreach ($controllerNodes as $ctrl) {
            $depCount = count($ctrl->getDependencies());
            if ($depCount > 5) {
                $suggestions[] = [
                    'type' => 'info',
                    'icon' => 'dependency',
                    'title' => 'Highly Coupled Controller',
                    'message' => "{$ctrl->getLabel()} has {$depCount} dependencies. Consider using action classes or service pattern.",
                    'component' => $ctrl->getLabel(),
                    'severity' => 'medium',
                ];
            }
        }

        if (count($nodes) > 0 && count($edges) === 0) {
            $suggestions[] = [
                'type' => 'info',
                'icon' => 'graph',
                'title' => 'No Relationships Found',
                'message' => 'No edges were detected between components. Ensure your code uses proper dependency injection and type hints.',
                'component' => 'global',
                'severity' => 'low',
            ];
        }

        $hasTests = array_filter($nodes, fn (Node $n) => count($n->getTestResults()) > 0);
        if (count($hasTests) === 0 && count($nodes) > 0) {
            $suggestions[] = [
                'type' => 'warning',
                'icon' => 'test',
                'title' => 'No Test Coverage',
                'message' => 'No tests detected. Consider adding tests to improve code reliability and health scores.',
                'component' => 'global',
                'severity' => 'high',
            ];
        }

        /** @var array{type: string, icon: string, title: string, message: string, component: string, severity: string} $a, $b */
        usort($suggestions, function (array $a, array $b): int {
            $order = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($order[$b['severity']] ?? 0) <=> ($order[$a['severity']] ?? 0);
        });

        $edgeTypes = [];
        foreach ($edges as $edge) {
            $t = $edge->getType();
            $edgeTypes[$t] = ($edgeTypes[$t] ?? 0) + 1;
        }

        $nodeTypes = [];
        foreach ($nodes as $node) {
            $t = $node->getType();
            $nodeTypes[$t] = ($nodeTypes[$t] ?? 0) + 1;
        }

        return response()->json([
            'summary' => [
                'totalNodes' => $stats['totalNodes'],
                'totalEdges' => $stats['totalEdges'],
                'averageDependencies' => $stats['averageDependencies'],
                'averageHealth' => $stats['averageHealth'],
                'healthyCount' => $health['healthyCount'],
                'moderateCount' => $health['moderateCount'],
                'unhealthyCount' => $health['unhealthyCount'],
                'godClassCount' => count($godClasses),
                'totalTests' => array_sum(array_map(fn (Node $n) => count($n->getTestResults()), $nodes)),
                'routeCount' => count($routeNodes),
                'suggestionCount' => count($suggestions),
            ],
            'architecture' => $graph->toArray(),
            'quality' => [
                'averageComplexity' => count($nodes) > 0 ? round(array_sum(array_map(fn (Node $n) => $n->getComplexityScore(), $nodes)) / count($nodes), 2) : 0,
                'averageHealthByType' => $avgHealthByType,
                'averageComplexityByType' => $avgComplexityByType,
                'godClasses' => $godClasses,
                'highComplexityCount' => count($highComplexity),
                'highDependencyCount' => count($highDepNodes),
            ],
            'performance' => [
                'nodeTypeCounts' => $nodeTypes,
                'edgeTypeCounts' => $edgeTypes,
                'totalRoutes' => count($routeNodes),
            ],
            'coverage' => [
                'overall' => count($nodes) > 0 ? round(count($hasTests) / count($nodes) * 100, 1) : 0,
                'testedCount' => count($hasTests),
                'untestedCount' => count($nodes) - count($hasTests),
            ],
            'database' => [
                'modelCount' => count($models),
                'totalRelationships' => array_sum(array_map(fn (Node $m) => count($m->getMetadata('relationships', [])), $models)),
            ],
            'suggestions' => $suggestions,
        ]);
    }

    private function hydrateGraph(array $data): ArchitectureGraph
    {
        $graph = new ArchitectureGraph;

        foreach ($data['nodes'] ?? [] as $nodeData) {
            $node = new Node(
                id: $nodeData['id'],
                label: $nodeData['label'],
                type: $nodeData['type'],
                namespace: $nodeData['namespace'],
                filePath: $nodeData['filePath'] ?? '',
            );

            $node->setHealthScore($nodeData['healthScore'] ?? 1.0);
            $node->setComplexityScore($nodeData['complexityScore'] ?? 0);

            foreach ($nodeData['metadata'] ?? [] as $key => $value) {
                $node->setMetadata($key, $value);
            }

            $graph->addNode($node);
        }

        foreach ($data['edges'] ?? [] as $edgeData) {
            $edge = new Edge(
                id: $edgeData['id'],
                sourceId: $edgeData['sourceId'],
                targetId: $edgeData['targetId'],
                type: $edgeData['type'],
                label: $edgeData['label'] ?? null,
            );

            $graph->addEdge($edge);
        }

        return $graph;
    }
}
