<?php

declare(strict_types=1);

it('serves the canvas view page', function () {
    $response = $this->get('/canvas');
    $response->assertStatus(200);
    $response->assertSee('Laravel Canvas');
});

it('serves the dashboard view page', function () {
    $response = $this->get('/canvas/dashboard');
    $response->assertStatus(200);
    $response->assertSee('Canvas Dashboard');
});

it('returns graph data from the api', function () {
    $response = $this->get('/api/canvas/graph');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'nodes', 'edges', 'nodeCount', 'edgeCount',
    ]);
});

it('returns dashboard stats from the api', function () {
    $response = $this->get('/api/canvas/dashboard');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'totalNodes', 'totalEdges',
    ]);
});

it('searches nodes from the api', function () {
    $response = $this->get('/api/canvas/search?q=test');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'results', 'total',
    ]);
});

it('returns health data from the api', function () {
    $response = $this->get('/api/canvas/health');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'graphHealth', 'godClasses',
    ]);
});

it('returns snapshot from the api', function () {
    $response = $this->postJson('/api/canvas/snapshot');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'snapshotId', 'graph',
    ]);
});

it('returns export from the api', function () {
    $response = $this->get('/api/canvas/export');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'exportedAt', 'graph', 'dashboard',
    ]);
});

it('filters nodes by type from the api', function () {
    $response = $this->get('/api/canvas/filter/model');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'type', 'nodes', 'total',
    ]);
});
