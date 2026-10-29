<?php

use App\Domain\Connect\Providers;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\MonitorController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\SimulationController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/internal/metrics', MetricsController::class);
Route::get('/health/live', fn () => ['status' => 'ok']);
Route::get('/health/ready', function () {
    DB::select('select 1');
    Redis::ping();

    return ['status' => 'ready'];
});
Route::prefix('api/v1')->group(function () {
    Route::get('/session', [AuthController::class, 'session']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::post('/workspaces', [WorkspaceController::class, 'store']);
        Route::post('/invites/accept', [WorkspaceController::class, 'accept']);
        Route::middleware('workspace')->group(function () {
            Route::patch('/workspace', [WorkspaceController::class, 'update']);
            Route::get('/members', [WorkspaceController::class, 'members']);
            Route::post('/members/invite', [WorkspaceController::class, 'invite']);
            Route::match(['PATCH', 'DELETE'], '/members/{id}', [WorkspaceController::class, 'changeMember'])->whereUuid('id');
            Route::get('/providers', fn () => Providers::catalog());
            Route::get('/overview', [MonitorController::class, 'overview']);
            Route::get('/events', [MonitorController::class, 'events']);
            Route::get('/deliveries', [MonitorController::class, 'deliveries']);
            Route::get('/deliveries/export', [MonitorController::class, 'export']);
            Route::get('/deliveries/{id}', [MonitorController::class, 'detail'])->whereUuid('id');
            Route::post('/deliveries/{id}/replay', [MonitorController::class, 'replay'])->whereUuid('id');
            Route::get('/logs/{kind}', [MonitorController::class, 'logs']);
            Route::get('/alerts', [AlertController::class, 'index']);
            Route::post('/alerts', [AlertController::class, 'store']);
            Route::post('/alerts/{id}/mute', [AlertController::class, 'mute'])->whereUuid('id');
            Route::get('/simulation', [SimulationController::class, 'index']);
            Route::post('/simulation/run', [SimulationController::class, 'run']);
            Route::post('/simulation/recover', [SimulationController::class, 'recover']);
            Route::post('/simulation/prepare', [SimulationController::class, 'prepare']);
            Route::post('/mappings/preview', [ResourceController::class, 'preview']);
            Route::post('/connections/{id}/execute', [ResourceController::class, 'execute'])->whereUuid('id');
            Route::post('/resources/{kind}/{id}/rotate', [ResourceController::class, 'rotate'])->whereUuid('id');
            Route::get('/resources/{kind}', [ResourceController::class, 'index']);
            Route::post('/resources/{kind}', [ResourceController::class, 'save']);
            Route::put('/resources/{kind}/{id}', [ResourceController::class, 'save'])->whereUuid('id');
            Route::delete('/resources/{kind}/{id}', [ResourceController::class, 'remove'])->whereUuid('id');
        });
    });
});
