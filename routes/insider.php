<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderDashboardController;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderOpcacheController;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderSessionController;

Route::middleware(['web', 'auth', 'role:super_admin'])
    ->prefix('insider')
    ->name('accelerator.insider.')
    ->group(function (): void {
        Route::get('/', [InsiderDashboardController::class, 'index'])->name('index');
        Route::get('/sessions', [InsiderSessionController::class, 'index'])->name('sessions');
        Route::post('/sessions/store', [InsiderSessionController::class, 'storeSession'])->name('sessions.store');
        Route::post('/sessions/regenerate', [InsiderSessionController::class, 'regenerate'])->name('sessions.regenerate');
        Route::get('/debug-opcache', [InsiderOpcacheController::class, 'debugOpcache'])->name('debug-opcache');
        Route::get('/stats', [InsiderOpcacheController::class, 'stats'])->name('stats');
    });
