<?php

use Workbench\Tests\TestCase;

uses(TestCase::class);

test('dashboard page returns success', function () {
    $this->get('/api/canvas/dashboard')->assertStatus(200);
});

test('graph endpoint returns success', function () {
    $this->get('/api/canvas/graph')->assertStatus(200);
});

test('search endpoint returns success', function () {
    $this->get('/api/canvas/search?q=user')->assertStatus(200);
});

test('filter endpoint returns success', function () {
    $this->get('/api/canvas/filter/model')->assertStatus(200);
});

test('health endpoint returns success', function () {
    $this->get('/api/canvas/health')->assertStatus(200);
});

test('export endpoint returns success', function () {
    $this->get('/api/canvas/export')->assertStatus(200);
});

test('snapshot can be created and listed', function () {
    $create = $this->postJson('/api/canvas/snapshot');
    $create->assertStatus(200);
    $create->assertJsonStructure(['snapshotId']);

    $list = $this->get('/api/canvas/snapshots');
    $list->assertStatus(200);
    $list->assertJsonStructure(['snapshots']);
});
