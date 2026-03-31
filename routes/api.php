<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DlqController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\NatsV2FeaturesController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/nats/v2/smoke', [NatsV2FeaturesController::class, 'smoke']);
Route::post('/nats/v2/rpc/preferences', [NatsV2FeaturesController::class, 'rpcPreferences']);

Route::get('/rooms', [RoomController::class, 'index']);
Route::post('/rooms', [RoomController::class, 'store']);
Route::post('/rooms/{room}/message', [RoomController::class, 'message']);
Route::post('/rooms/{room}/schedule', [RoomController::class, 'schedule']);
Route::get('/rooms/{room}/history', [RoomController::class, 'history']);
Route::get('/analytics/room/{room}', [AnalyticsController::class, 'room']);
Route::get('/dlq', [DlqController::class, 'index']);
Route::get('/metrics', MetricsController::class);
