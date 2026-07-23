<?php

use Workbench\Tests\TestCase;

uses(TestCase::class);

test('canvas:scan artisan command succeeds', function () {
    $this->artisan('canvas:scan')
        ->expectsOutputToContain('Nodes discovered')
        ->assertSuccessful();
});

test('canvas:scan outputs component counts', function () {
    $this->artisan('canvas:scan')
        ->expectsOutputToContain('Nodes discovered')
        ->expectsOutputToContain('Edges mapped')
        ->expectsOutputToContain('Average health score')
        ->assertSuccessful();
});
