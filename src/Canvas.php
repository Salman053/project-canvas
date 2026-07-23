<?php

declare(strict_types=1);

namespace Salman053\Canvas;

use Salman053\Canvas\Data\ArchitectureGraph;
use Salman053\Canvas\Scanners\CodebaseScanner;
use Salman053\Canvas\Services\GraphService;

class Canvas
{
    private CodebaseScanner $scanner;

    private GraphService $graphService;

    private ?ArchitectureGraph $graph = null;

    public function __construct()
    {
        $this->scanner = new CodebaseScanner;
        $this->graphService = new GraphService;
    }

    public function scan(): ArchitectureGraph
    {
        $this->graph = $this->scanner->scan();
        $this->graph = $this->graphService->enrichGraph($this->graph);

        return $this->graph;
    }

    public function getGraph(): ?ArchitectureGraph
    {
        return $this->graph;
    }

    public function getDashboardStats(): array
    {
        if (! $this->graph) {
            $this->scan();
        }

        return $this->graphService->getDashboardStats($this->graph);
    }

    public function takeSnapshot(string $label = ''): string
    {
        if (! $this->graph) {
            $this->scan();
        }

        return $this->graph->takeSnapshot($label);
    }

    public function export(): array
    {
        if (! $this->graph) {
            $this->scan();
        }

        return [
            'exportedAt' => now()->toIso8601String(),
            'graph' => $this->graph->toArray(),
            'dashboard' => $this->getDashboardStats(),
        ];
    }
}
