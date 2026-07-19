<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WireNinja\Accelerator\Http\Controllers\Telemetry\TelemetryController;

Route::middleware(['web', 'auth', 'role:super_admin'])
    ->prefix('insider/telemetry')
    ->name('accelerator.telemetry.')
    ->group(function (): void {
        Route::get('/', [TelemetryController::class, 'index'])->name('index');
        Route::get('/{id}', [TelemetryController::class, 'show'])->name('show')->whereNumber('id');
        Route::post('/{id}/status', [TelemetryController::class, 'updateStatus'])->name('status')->whereNumber('id');
    });
