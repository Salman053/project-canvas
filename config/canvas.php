<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | WebSocket Server
    |--------------------------------------------------------------------------
    */
    'websocket' => [
        'host' => env('CANVAS_WS_HOST', '127.0.0.1'),
        'port' => (int) env('CANVAS_WS_PORT', 8081),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Server
    |--------------------------------------------------------------------------
    */
    'http' => [
        'port' => (int) env('CANVAS_HTTP_PORT', 8080),
    ],

    /*
    |--------------------------------------------------------------------------
    | Visualization Settings
    |--------------------------------------------------------------------------
    */
    'visualization' => [
        'particle_count' => (int) env('CANVAS_PARTICLES', 2000),
        'node_spacing' => (float) env('CANVAS_NODE_SPACING', 8.0),
        'animation_speed' => (float) env('CANVAS_ANIMATION_SPEED', 1.0),
        'background_color' => env('CANVAS_BG_COLOR', '#0a0a1a'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scanning Options
    |--------------------------------------------------------------------------
    */
    'scanning' => [
        'paths' => [
            'models' => [app_path('Models')],
            'controllers' => [app_path('Http/Controllers')],
            'jobs' => [app_path('Jobs')],
            'listeners' => [app_path('Listeners')],
            'policies' => [app_path('Policies')],
            'middleware' => [app_path('Http/Middleware')],
            'providers' => [app_path('Providers')],
        ],
        'max_methods_per_class' => 30,
        'max_dependencies_per_node' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Thresholds
    |--------------------------------------------------------------------------
    */
    'health' => [
        'healthy_threshold' => 0.8,
        'moderate_threshold' => 0.5,
        'god_class_threshold' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'path' => storage_path('app/canvas-graph.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Watcher
    |--------------------------------------------------------------------------
    */
    'test_watcher' => [
        'log_paths' => [
            storage_path('logs/laravel.log'),
        ],
        'poll_interval' => 1,
    ],

];
