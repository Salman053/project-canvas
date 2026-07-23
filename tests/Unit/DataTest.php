<?php

declare(strict_types=1);

use Salman053\Canvas\Data\ArchitectureGraph;
use Salman053\Canvas\Data\Edge;
use Salman053\Canvas\Data\Node;

it('creates a node with required properties', function () {
    $node = new Node('test-1', 'User', 'model', 'App\Models\User', '/app/Models/User.php');

    expect($node->getId())->toBe('test-1');
    expect($node->getLabel())->toBe('User');
    expect($node->getType())->toBe('model');
    expect($node->getNamespace())->toBe('App\Models\User');
});

it('sets and gets metadata on a node', function () {
    $node = new Node('test-1', 'User', 'model', 'App\Models\User', '');

    $node->setMetadata('table', 'users');
    expect($node->getMetadata('table'))->toBe('users');
    expect($node->getAllMetadata())->toHaveKey('table');
});

it('calculates health score based on test results', function () {
    $node = new Node('test-1', 'User', 'model', 'App\Models\User', '');

    $node->addTestResult('test one', true);
    expect($node->getTestPassRate())->toBe(1.0);

    $node->addTestResult('test two', false);
    expect($node->getTestPassRate())->toBe(0.5);

    $node->addTestResult('test three', true);
    expect(round($node->getTestPassRate(), 2))->toBe(0.67);
});

it('returns correct health colors', function () {
    $node = new Node('test-1', 'User', 'model', 'App\Models\User', '');

    $node->setHealthScore(0.9);
    expect($node->getHealthColor())->toBe('#00ff88');

    $node->setHealthScore(0.6);
    expect($node->getHealthColor())->toBe('#ffaa00');

    $node->setHealthScore(0.3);
    expect($node->getHealthColor())->toBe('#ff3355');
});

it('manages dependencies and dependents', function () {
    $node = new Node('test-1', 'User', 'model', 'App\Models\User', '');

    $node->addDependency('dep-1');
    $node->addDependency('dep-2');
    expect($node->getDependencies())->toHaveCount(2);

    $node->addDependent('dep-3');
    expect($node->getDependents())->toHaveCount(1);
});

it('creates an edge with required properties', function () {
    $edge = new Edge('edge-1', 'source-1', 'target-1', 'dependency', 'uses');

    expect($edge->getId())->toBe('edge-1');
    expect($edge->getSourceId())->toBe('source-1');
    expect($edge->getTargetId())->toBe('target-1');
    expect($edge->getType())->toBe('dependency');
    expect($edge->getLabel())->toBe('uses');
});

it('returns correct edge colors by type', function () {
    $e1 = new Edge('e1', 'a', 'b', 'relationship');
    expect($e1->getColor())->toBe('#00ccff');

    $e2 = new Edge('e2', 'a', 'b', 'dependency');
    expect($e2->getColor())->toBe('#ff8800');

    $e3 = new Edge('e3', 'a', 'b', 'event');
    expect($e3->getColor())->toBe('#aa66ff');

    $e4 = new Edge('e4', 'a', 'b', 'route');
    expect($e4->getColor())->toBe('#66ddaa');

    $e5 = new Edge('e5', 'a', 'b', 'test');
    expect($e5->getColor())->toBe('#ff66aa');
});

it('builds an architecture graph with nodes and edges', function () {
    $graph = new ArchitectureGraph;

    $n1 = new Node('n1', 'User', 'model', 'App\Models\User', '');
    $n2 = new Node('n2', 'Post', 'model', 'App\Models\Post', '');
    $graph->addNode($n1);
    $graph->addNode($n2);

    expect($graph->getNodeCount())->toBe(2);
    expect($graph->getNode('n1'))->toBe($n1);

    $edge = new Edge('e1', 'n1', 'n2', 'relationship', 'hasMany');
    $graph->addEdge($edge);

    expect($graph->getEdgeCount())->toBe(1);
    expect($n1->getDependencies())->toContain('n2');
    expect($n2->getDependents())->toContain('n1');
});

it('gets nodes by type from graph', function () {
    $graph = new ArchitectureGraph;

    $graph->addNode(new Node('n1', 'User', 'model', '', ''));
    $graph->addNode(new Node('n2', 'Post', 'model', '', ''));
    $graph->addNode(new Node('n3', 'UserController', 'controller', '', ''));

    expect($graph->getNodesByType('model'))->toHaveCount(2);
    expect($graph->getNodesByType('controller'))->toHaveCount(1);
});

it('takes and retrieves snapshots', function () {
    $graph = new ArchitectureGraph;

    $graph->addNode(new Node('n1', 'User', 'model', '', ''));
    $graph->addNode(new Node('n2', 'Post', 'model', '', ''));

    $snapId = $graph->takeSnapshot('test-snapshot');
    expect($snapId)->not->toBeNull();

    $snapshots = $graph->getSnapshots();
    expect($snapshots)->toHaveCount(1);
    expect($snapshots[0]['label'])->toBe('test-snapshot');

    $retrieved = $graph->getSnapshot($snapId);
    expect($retrieved)->not->toBeNull();
    expect($retrieved['nodeCount'])->toBe(2);
});
