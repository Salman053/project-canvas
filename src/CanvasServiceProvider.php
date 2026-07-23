<?php

declare(strict_types=1);

namespace Salman053\Canvas;

use Illuminate\Support\ServiceProvider;
use Salman053\Canvas\Console\Commands\CanvasScanCommand;
use Salman053\Canvas\Console\Commands\CanvasServeCommand;

class CanvasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/canvas.php', 'canvas');

        $this->app->singleton(Canvas::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/canvas.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views/canvas', 'canvas');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/canvas.php' => config_path('canvas.php'),
            ], ['canvas', 'canvas-config']);

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/canvas'),
            ], ['canvas', 'canvas-views']);

            $this->publishes([
                __DIR__.'/../public/vendor/canvas' => public_path('vendor/canvas'),
            ], ['canvas', 'canvas-assets']);

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], ['canvas', 'canvas-migrations']);

            $this->commands([
                CanvasScanCommand::class,
                CanvasServeCommand::class,
            ]);
        }
    }
}
