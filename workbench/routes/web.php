<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\PostController;
use Workbench\App\Http\Controllers\UserController;

Route::get('/', fn () => view('welcome'));

Route::prefix('api')->group(function () {
    Route::resource('posts', PostController::class)->except(['create', 'edit']);
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
});
