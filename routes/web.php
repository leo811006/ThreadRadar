<?php

use App\Http\Controllers\Auth\SpaAuthController;
use Illuminate\Support\Facades\Route;

// Sanctum SPA cookie-based 認證端點（session-based，非 API token 發放）
Route::post('/login', [SpaAuthController::class, 'login']);
Route::post('/logout', [SpaAuthController::class, 'logout']);
Route::get('/me', [SpaAuthController::class, 'me']);

// Vue 3 SPA：前台 Dashboard 入口。SPA 內部路由（/keywords、/posts 等）皆由 Vue Router 接管，
// 因此除了 /admin（Filament 後台）、/api（REST API）、上述認證端點以外的路徑一律 fallback 回這個入口頁。
Route::view('/{any}', 'app')
    ->where('any', '^(?!admin|api|login|logout|me).*$')
    ->name('spa');
