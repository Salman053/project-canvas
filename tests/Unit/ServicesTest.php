<?php

declare(strict_types=1);

use VendorName\Canvas\Data\ArchitectureGraph;
use VendorName\Canvas\Data\Node;
use VendorName\Canvas\Services\ComplexityAnalyzer;
use VendorName\Canvas\Services\GraphService;
use VendorName\Canvas\Services\HealthService;

it('analyzes node complexity', function () {
    $analyzer = new ComplexityAnalyzer;

    $tempFile = tempnam(sys_get_temp_dir(), 'canvas_test_');
    file_put_contents($tempFile, '<?php class Test {
        public function foo() {
            if (true) { return 1; }
            foreach ([] as $x) { echo $x; }
        }
    }');

    $node = new Node('test', 'Test', 'controller', 'App\Test', $tempFile);
    $score = $analyzer->analyze($node);

    expect($score)->toBeGreaterThan(0);
    unlink($tempFile);
});

it('calculates cyclomatic complexity', function () {
    $analyzer = new ComplexityAnalyzer;

    $tempFile = tempnam(sys_get_temp_dir(), 'canvas_test_');
    file_put_contents($tempFile, '<?php class Test {
        public function foo($x) {
            if ($x > 0) { return 1; }
            elseif ($x < 0) { return -1; }
            else { return 0; }
        }
    }');

    $node = new Node('test', 'Test', 'controller', 'App\Test', $tempFile);
    $complexity = $analyzer->getCyclomaticComplexity($node);

    expect($complexity)->toBeGreaterThanOrEqual(3);
    unlink($tempFile);
});

it('calculates node health', function () {
    $service = new HealthService;

    $node = new Node('test', 'Test', 'controller', 'App\Test', '');
    $node->addTestResult('test1', true);
    $node->addTestResult('test2', true);

    $health = $service->calculateNodeHealth($node);
    expect($health)->toBeGreaterThan(0);
    expect($health)->toBeLessThanOrEqual(1);
});

it('calculates graph health summary', function () {
    $service = new HealthService;
    $graph = new ArchitectureGraph;

    $n1 = new Node('n1', 'Good', 'controller', '', '');
    $n1->setHealthScore(0.9);
    $graph->addNode($n1);

    $n2 = new Node('n2', 'Mid', 'controller', '', '');
    $n2->setHealthScore(0.6);
    $graph->addNode($n2);

    $n3 = new Node('n3', 'Bad', 'controller', '', '');
    $n3->setHealthScore(0.3);
    $graph->addNode($n3);

    $summary = $service->calculateGraphHealth($graph);

    expect($summary['totalNodes'])->toBe(3);
    expect($summary['healthyCount'])->toBe(1);
    expect($summary['moderateCount'])->toBe(1);
    expect($summary['unhealthyCount'])->toBe(1);
    expect($summary['averageHealth'])->toBeGreaterThan(0);
});

it('enriches graph with health and complexity', function () {
    $service = new GraphService;
    $graph = new ArchitectureGraph;

    $tempFile = tempnam(sys_get_temp_dir(), 'canvas_test_');
    file_put_contents($tempFile, '<?php class Test { public function foo() { return 1; } }');

    $node = new Node('test', 'Test', 'controller', 'App\Test', $tempFile);
    $graph->addNode($node);

    $graph = $service->enrichGraph($graph);

    $enriched = $graph->getNode('test');
    expect($enriched->getComplexityScore())->toBeGreaterThan(0);
    expect($enriched->getHealthScore())->toBeGreaterThan(0);

    unlink($tempFile);
});

it('searches nodes by label and namespace', function () {
    $service = new GraphService;
    $graph = new ArchitectureGraph;

    $graph->addNode(new Node('n1', 'UserController', 'controller', 'App\Http\Controllers\UserController', ''));
    $graph->addNode(new Node('n2', 'PostController', 'controller', 'App\Http\Controllers\PostController', ''));
    $graph->addNode(new Node('n3', 'User', 'model', 'App\Models\User', ''));

    $results = $service->searchNodes($graph, 'user');
    expect($results)->toHaveCount(2);

    $results = $service->searchNodes($graph, 'post');
    expect($results)->toHaveCount(1);
});
