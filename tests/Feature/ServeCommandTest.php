<?php

declare(strict_types=1);

it('registers the canvas:serve artisan command', function () {
    $this->artisan('canvas:serve --help')
        ->assertSuccessful();
});

it('canvas:serve shows usage information', function () {
    $this->artisan('canvas:serve --help')
        ->expectsOutputToContain('canvas:serve');
});
