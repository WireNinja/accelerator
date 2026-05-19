<?php

use Illuminate\Support\Facades\Route;
use WireNinja\Accelerator\Http\Controllers\Auth\GoogleAuthController;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderDashboardController;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderOpcacheController;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderSessionController;

Route::middleware(['web'])->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::middleware(['auth', 'role:super_admin'])->prefix('insider')->name('accelerator.insider.')->group(function (): void {
        Route::get('/', [InsiderDashboardController::class, 'index'])->name('index');

        Route::get('/sessions', [InsiderSessionController::class, 'index'])->name('sessions');
        Route::post('/sessions/store', [InsiderSessionController::class, 'storeSession'])->name('sessions.store');
        Route::post('/sessions/regenerate', [InsiderSessionController::class, 'regenerate'])->name('sessions.regenerate');

        Route::get('/debug-opcache', [InsiderOpcacheController::class, 'debugOpcache'])->name('debug-opcache');
        Route::get('/stats', [InsiderOpcacheController::class, 'stats'])->name('stats');

        Route::get('exception', function () {
            throw new RuntimeException('Test Exception');
        });
    });

    if (app()->isLocal() || app()->hasDebugModeEnabled()) {
        // This route must be registered manually in reverse proxy like nginx or Caddy, to optimize response time.
        // This route only available in local environment to enhance DX, and should not be used in production environment for security hardening.
        Route::get('/build/sw.js', fn () => response(headers: [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Service-Worker-Allowed' => '/',
            'Content-Type' => 'application/javascript',
        ])->file(
            public_path('sw.js'),
            [
                'Content-Type' => 'application/javascript',
            ]
        ));
    }
});
