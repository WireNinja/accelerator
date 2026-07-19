<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WireNinja\Accelerator\Http\Controllers\Telemetry\TelemetryController;

Route::middleware(['web', 'auth', 'role:super_admin'])
    ->prefix('insider/telemetry')
    ->name('accelerator.telemetry.')
    ->group(function (): void {
        Route::get('/', [TelemetryController::class, 'index'])->name('index');
        Route::get('/logs', [TelemetryController::class, 'logs'])->name('logs');
        Route::get('/{id}', [TelemetryController::class, 'show'])->name('show')->whereNumber('id');
        Route::post('/{id}/resolve', [TelemetryController::class, 'resolve'])->name('resolve')->whereNumber('id');
        Route::post('/{id}/mute', [TelemetryController::class, 'mute'])->name('mute')->whereNumber('id');
        Route::post('/{id}/reopen', [TelemetryController::class, 'reopen'])->name('reopen')->whereNumber('id');
    });
