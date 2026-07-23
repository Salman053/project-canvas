<?php

declare(strict_types=1);

namespace Salman053\Canvas\Data;

class ArchitectureGraph
{
    private array $nodes = [];

    private array $edges = [];

    private array $snapshots = [];

    public function addNode(Node $node): void
    {
        $this->nodes[$node->getId()] = $node;
    }

    public function getNode(string $id): ?Node
    {
        return $this->nodes[$id] ?? null;
    }

    public function getNodes(): array
    {
        return array_values($this->nodes);
    }

    public function getNodesByType(string $type): array
    {
        return array_values(
            array_filter($this->nodes, fn (Node $n) => $n->getType() === $type),
        );
    }

    public function removeNode(string $id): void
    {
        unset($this->nodes[$id]);
        $this->edges = array_filter(
            $this->edges,
            fn (Edge $e) => $e->getSourceId() !== $id && $e->getTargetId() !== $id,
        );
    }

    public function addEdge(Edge $edge): void
    {
        $this->edges[$edge->getId()] = $edge;

        if ($source = $this->getNode($edge->getSourceId())) {
            $source->addDependency($edge->getTargetId());
        }

        if ($target = $this->getNode($edge->getTargetId())) {
            $target->addDependent($edge->getSourceId());
        }
    }

    public function getEdge(string $id): ?Edge
    {
        return $this->edges[$id] ?? null;
    }

    public function getEdges(): array
    {
        return array_values($this->edges);
    }

    public function getEdgesByType(string $type): array
    {
        return array_values(
            array_filter($this->edges, fn (Edge $e) => $e->getType() === $type),
        );
    }

    public function getEdgesForNode(string $nodeId): array
    {
        return array_values(
            array_filter(
                $this->edges,
                fn (Edge $e) => $e->getSourceId() === $nodeId || $e->getTargetId() === $nodeId,
            ),
        );
    }

    public function getNodeCount(): int
    {
        return count($this->nodes);
    }

    public function getEdgeCount(): int
    {
        return count($this->edges);
    }

    public function getAverageDependencies(): float
    {
        if (empty($this->nodes)) {
            return 0.0;
        }

        $total = array_sum(
            array_map(fn (Node $n) => count($n->getDependencies()), $this->nodes),
        );

        return $total / count($this->nodes);
    }

    public function getAverageHealth(): float
    {
        if (empty($this->nodes)) {
            return 0.0;
        }

        $total = array_sum(
            array_map(fn (Node $n) => $n->getHealthScore(), $this->nodes),
        );

        return $total / count($this->nodes);
    }

    public function takeSnapshot(string $label = ''): string
    {
        $id = 'snap_'.bin2hex(random_bytes(8));
        $this->snapshots[$id] = [
            'id' => $id,
            'label' => $label,
            'timestamp' => now()->toIso8601String(),
            'nodeCount' => $this->getNodeCount(),
            'edgeCount' => $this->getEdgeCount(),
            'averageHealth' => $this->getAverageHealth(),
            'averageDependencies' => $this->getAverageDependencies(),
            'nodes' => array_map(fn (Node $n) => $n->toArray(), $this->nodes),
            'edges' => array_map(fn (Edge $e) => $e->toArray(), $this->edges),
        ];

        return $id;
    }

    public function getSnapshots(): array
    {
        return array_values($this->snapshots);
    }

    public function getSnapshot(string $id): ?array
    {
        return $this->snapshots[$id] ?? null;
    }

    public function toArray(): array
    {
        return [
            'nodeCount' => $this->getNodeCount(),
            'edgeCount' => $this->getEdgeCount(),
            'averageDependencies' => $this->getAverageDependencies(),
            'averageHealth' => $this->getAverageHealth(),
            'nodes' => array_values(array_map(fn (Node $n) => $n->toArray(), $this->nodes)),
            'edges' => array_values(array_map(fn (Edge $e) => $e->toArray(), $this->edges)),
        ];
    }
}
