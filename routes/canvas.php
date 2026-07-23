<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Salman053\Canvas\Http\Controllers\CanvasApiController;

Route::prefix('api/canvas')->group(function () {
    Route::get('/graph', [CanvasApiController::class, 'graph']);
    Route::get('/graph/node/{id}', [CanvasApiController::class, 'node'])->where('id', '.*');
    Route::get('/search', [CanvasApiController::class, 'search']);
    Route::get('/dashboard', [CanvasApiController::class, 'dashboard']);
    Route::get('/health', [CanvasApiController::class, 'health']);
    Route::get('/filter/{type}', [CanvasApiController::class, 'filter']);
    Route::get('/heatmap', [CanvasApiController::class, 'heatmap']);
    Route::post('/snapshot', [CanvasApiController::class, 'snapshot']);
    Route::get('/snapshots', [CanvasApiController::class, 'snapshotList']);
    Route::get('/export', [CanvasApiController::class, 'export']);
    Route::get('/analytics', [CanvasApiController::class, 'analytics']);
});

Route::get('/canvas/assets/{path}', function (string $path) {
    $filePath = __DIR__.'/../public/vendor/canvas/'.$path;

    if (! file_exists($filePath)) {
        abort(404);
    }

    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'woff2' => 'font/woff2',
    ];

    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

    return response(file_get_contents($filePath), 200, ['Content-Type' => $mime]);
})->where('path', '.*');

Route::get('/canvas', function () {
    return view('canvas::graph');
});

Route::get('/canvas/dashboard', function () {
    return view('canvas::dashboard');
});
