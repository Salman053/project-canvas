<?php

namespace Workbench\Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use VendorName\Canvas\CanvasServiceProvider;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            CanvasServiceProvider::class,
        ];
    }
}
