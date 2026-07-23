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
});

Route::get('/canvas', function () {
    return view('canvas::graph');
});

Route::get('/canvas/dashboard', function () {
    return view('canvas::dashboard');
});
