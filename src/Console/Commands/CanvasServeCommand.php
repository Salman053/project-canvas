<?php

declare(strict_types=1);

namespace Salman053\Canvas\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Salman053\Canvas\Scanners\CodebaseScanner;
use Salman053\Canvas\Server\WebSocketServer;
use Salman053\Canvas\Services\GraphService;

class CanvasServeCommand extends Command
{
    protected $signature = 'canvas:serve
        {--host=127.0.0.1 : The host to bind the visualization server to}
        {--port=8081 : The port to bind the WebSocket server to}
        {--http-port=8080 : The port to bind the HTTP server to}';

    protected $description = 'Start the Laravel Canvas 3D visualization server';

    private ?WebSocketServer $wsServer = null;

    public function handle(CodebaseScanner $scanner, GraphService $graphService): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');
        $httpPort = (int) $this->option('http-port');

        $this->components->info('Starting Laravel Canvas...');

        $this->components->twoColumnDetail('Scanning codebase', '...');
        $graph = $scanner->scan();
        $graph = $graphService->enrichGraph($graph);

        $stats = $graphService->getDashboardStats($graph);
        $this->components->twoColumnDetail('Components found', (string) $stats['totalNodes']);
        $this->components->twoColumnDetail('Relationships mapped', (string) $stats['totalEdges']);

        $this->newLine();
        $this->components->info('Starting WebSocket server...');

        try {
            $this->wsServer = new WebSocketServer($host, $port);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('WebSocket', "ws://{$host}:{$port}");
        $this->components->twoColumnDetail('Dashboard', "http://{$host}:{$httpPort}/canvas");
        $this->newLine();

        $this->components->info(
            'Canvas visualization is running. Open your browser and run your tests to see live updates!',
        );

        $this->newLine();
        $this->line('  <options=bold>How to use:</>');
        $this->line('  - Open <href=http://localhost:8080/canvas>http://localhost:8080/canvas</>');
        $this->line('  - Run <options=bold>phpunit</> or <options=bold>pest</> in another terminal');
        $this->line('  - Watch the graph come alive with test results');
        $this->line('');

        $graphData = $graph->toArray();
        $graphData['dashboard'] = $stats;

        file_put_contents(
            storage_path('app/canvas-graph.json'),
            json_encode($graphData, JSON_PRETTY_PRINT),
        );

        $this->line('  <fg=gray>Graph data cached to storage/app/canvas-graph.json</>');
        $this->newLine();

        $this->wsServer->start();

        return self::SUCCESS;
    }
}
