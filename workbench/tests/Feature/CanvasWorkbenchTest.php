<?php

uses(Workbench\Tests\TestCase::class);

use VendorName\Canvas\Facades\Canvas;

test('facade scan returns nodes', function () {
    $graph = Canvas::scan();

    expect($graph->getNodeCount())->toBeGreaterThan(0);
    expect($graph->getEdgeCount())->toBeGreaterThan(0);
});

test('facade scan discovers routes', function () {
    $graph = Canvas::scan();

    $edges = $graph->getEdgesByType('route');

    expect($edges)->not->toBeEmpty();
});
