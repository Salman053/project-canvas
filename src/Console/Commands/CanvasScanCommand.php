<?php

declare(strict_types=1);

namespace Salman053\Canvas\Console\Commands;

use Illuminate\Console\Command;
use Salman053\Canvas\Scanners\CodebaseScanner;
use Salman053\Canvas\Services\GraphService;

class CanvasScanCommand extends Command
{
    protected $signature = 'canvas:scan
        {--json : Output the architecture graph as JSON}
        {--output= : Path to write the JSON output file}
        {--snapshot : Take a named snapshot of the current state}
        {--label= : Label for the snapshot}';

    protected $description = 'Scan the Laravel codebase and build an architecture graph';

    public function handle(CodebaseScanner $scanner, GraphService $graphService): int
    {
        $this->components->info('Scanning codebase...');

        $this->components->twoColumnDetail('Scanning Models', '...');
        $graph = $scanner->scan();

        $this->components->twoColumnDetail('Analyzing complexity & health', '...');
        $graph = $graphService->enrichGraph($graph);

        $stats = $graphService->getDashboardStats($graph);

        $this->newLine();
        $this->components->twoColumnDetail('Nodes discovered', (string) $stats['totalNodes']);
        $this->components->twoColumnDetail('Edges mapped', (string) $stats['totalEdges']);
        $this->components->twoColumnDetail('Average dependencies', (string) $stats['averageDependencies']);
        $this->components->twoColumnDetail('Average health score', (string) $stats['averageHealth']);
        $this->components->twoColumnDetail('Healthy components', (string) $stats['healthSummary']['healthyCount']);
        $this->components->twoColumnDetail('Need attention', (string) $stats['healthSummary']['moderateCount']);
        $this->components->twoColumnDetail('God classes identified', (string) count($stats['godClasses']));

        if (! empty($stats['godClasses'])) {
            $this->newLine();
            $this->components->warn('God classes detected (high complexity):');

            foreach (array_slice($stats['godClasses'], 0, 5) as $god) {
                $this->components->bullet(
                    "{$god['node']['label']} (score: {$god['godScore']}, complexity: {$god['complexity']})",
                );
            }
        }

        if ($this->option('snapshot')) {
            $snapshotId = $graph->takeSnapshot(
                $this->option('label') ?? 'manual-snapshot-'.now()->format('YmdHis'),
            );
            $this->components->twoColumnDetail('Snapshot', $snapshotId);
        }

        $output = $graph->toArray();

        if ($this->option('json')) {
            $json = json_encode($output, JSON_PRETTY_PRINT);

            if ($path = $this->option('output')) {
                file_put_contents($path, $json);
                $this->components->twoColumnDetail('Output written', $path);
            } else {
                $this->line($json);
            }
        }

        return self::SUCCESS;
    }
}
