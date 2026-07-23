<?php

declare(strict_types=1);

namespace VendorName\Canvas\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use VendorName\Canvas\CanvasServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CanvasServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('canvas.websocket.host', '127.0.0.1');
        $app['config']->set('canvas.websocket.port', 8081);
    }
}
