<?php

uses(Workbench\Tests\TestCase::class);

test('returns graph data via api', function () {
    $response = $this->get('/api/canvas/graph');
    $response->assertStatus(200);

    $data = $response->json();

    expect($data['nodeCount'])->toBeGreaterThan(0);
    expect($data['edgeCount'])->toBeGreaterThan(0);
});

test('returns dashboard stats', function () {
    $response = $this->get('/api/canvas/dashboard');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'totalNodes', 'totalEdges', 'averageDependencies', 'averageHealth',
        'nodeTypeCounts', 'healthSummary',
    ]);
});

test('searches for components', function () {
    $response = $this->get('/api/canvas/search?q=user');
    $response->assertStatus(200);

    $data = $response->json();
    expect($data['total'])->toBeGreaterThanOrEqual(1);
});

test('filters components by type', function () {
    $response = $this->get('/api/canvas/filter/model');
    $response->assertStatus(200);

    $data = $response->json();
    expect($data['type'])->toBe('model');
    expect($data['total'])->toBeGreaterThanOrEqual(1);
});

test('exports architecture', function () {
    $response = $this->get('/api/canvas/export');
    $response->assertStatus(200);

    $data = $response->json();
    expect($data['exportedAt'])->not->toBeEmpty();
    expect($data['graph']['nodeCount'])->toBeGreaterThan(0);
});

test('takes a snapshot', function () {
    $response = $this->postJson('/api/canvas/snapshot');
    $response->assertStatus(200);

    $data = $response->json();
    expect($data['snapshotId'])->not->toBeEmpty();
});

test('lists snapshots', function () {
    $this->postJson('/api/canvas/snapshot');

    $response = $this->get('/api/canvas/snapshots');
    $response->assertStatus(200);
    $response->assertJsonStructure(['snapshots']);
});
