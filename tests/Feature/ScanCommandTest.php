<?php

declare(strict_types=1);

it('registers the canvas:scan artisan command', function () {
    $this->artisan('canvas:scan')
        ->expectsOutputToContain('Scanning codebase...')
        ->assertSuccessful();
});

it('canvas:scan outputs node count', function () {
    $this->artisan('canvas:scan')
        ->expectsOutputToContain('Nodes discovered')
        ->assertSuccessful();
});

it('canvas:scan accepts --json flag', function () {
    $this->artisan('canvas:scan --json')
        ->assertSuccessful();
});
