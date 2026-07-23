<?php

uses(Workbench\Tests\TestCase::class);

it('can access app', function () {
    expect(app())->not->toBeNull();
});

it('can call get', function () {
    $response = $this->get('/');
    expect($response->status())->toBe(200);
});
