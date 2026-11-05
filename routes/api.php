<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
| All routes are prefixed with /api and rate limited by the "api" limiter (see AppServiceProvider).
*/

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()
        ->name('products.restore');
    Route::apiResource('products', ProductController::class);

    Route::get('categories', [LookupController::class, 'categories'])->name('categories.index');
    Route::get('suppliers', [LookupController::class, 'suppliers'])->name('suppliers.index');
});
