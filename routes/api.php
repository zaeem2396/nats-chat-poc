<?php

use App\Http\Controllers\DlqController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{order}', [OrderController::class, 'show']);

Route::get('/metrics', OrderMetricsController::class);
Route::get('/dlq', [DlqController::class, 'index']);
