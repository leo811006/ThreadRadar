<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KeywordController;
use App\Http\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('keywords', KeywordController::class);
    Route::post('keywords/{keyword}/crawl-now', [KeywordController::class, 'crawlNow']);
    Route::get('posts', [PostController::class, 'index']);
    Route::get('posts/{post}', [PostController::class, 'show']);
    Route::get('dashboard', DashboardController::class);
});
