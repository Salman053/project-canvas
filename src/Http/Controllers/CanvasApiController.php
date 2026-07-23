<?php

declare(strict_types=1);

namespace VendorName\Canvas\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VendorName\Canvas\Data\ArchitectureGraph;
use VendorName\Canvas\Data\Edge;
use VendorName\Canvas\Data\Node;
use VendorName\Canvas\Scanners\CodebaseScanner;
use VendorName\Canvas\Scanners\GitAnalyzer;
use VendorName\Canvas\Services\GraphService;
use VendorName\Canvas\Services\HealthService;

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
                fn ($e) => $e->toArray(),
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
            'results' => array_map(fn ($n) => $n->toArray(), $results),
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
            'nodes' => array_map(fn ($n) => $n->toArray(), $filtered),
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
