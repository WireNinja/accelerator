<?php

use Illuminate\Support\Facades\Route;
use WireNinja\Accelerator\Http\Controllers\Auth\GoogleAuthController;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderDashboardController;

Route::middleware(['web'])->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::middleware(['auth', 'role:super_admin'])->prefix('insider')->name('accelerator.insider.')->group(function (): void {
        Route::get('/', [InsiderDashboardController::class, 'index'])->name('index');
        Route::post('/session', [InsiderDashboardController::class, 'storeSession'])->name('session.store');
        Route::post('/regenerate', [InsiderDashboardController::class, 'regenerate'])->name('regenerate');

        Route::get('/debug-opcache', function () {
            return response()->json([
                'opcache' => opcache_get_status(false),
                'configuration' => opcache_get_configuration(),
            ]);
        });

        Route::get('/stats', function () {
            $opcacheStatus = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
            $isOpcacheStatusAvailable = is_array($opcacheStatus);
            $jitRuntime = $isOpcacheStatusAvailable && isset($opcacheStatus['jit']) && is_array($opcacheStatus['jit'])
                ? $opcacheStatus['jit']
                : null;

            return response()->json([
                'timestamp' => now()->toIso8601String(),
                'runtime' => [
                    'php_version' => PHP_VERSION,
                    'php_sapi' => PHP_SAPI,
                    'app_env' => config('app.env'),
                    'pid' => getmypid(),
                    'memory_usage_bytes' => memory_get_usage(true),
                    'memory_peak_usage_bytes' => memory_get_peak_usage(true),
                ],
                'opcache' => [
                    'loaded' => extension_loaded('Zend OPcache'),
                    'enabled_ini' => ini_get('opcache.enable') === '1',
                    'enabled_cli_ini' => ini_get('opcache.enable_cli') === '1',
                    'status_available' => $isOpcacheStatusAvailable,
                    'enabled_runtime' => $isOpcacheStatusAvailable ? (bool) ($opcacheStatus['opcache_enabled'] ?? false) : false,
                    'cache_full' => $isOpcacheStatusAvailable ? (bool) ($opcacheStatus['cache_full'] ?? false) : null,
                    'restart_pending' => $isOpcacheStatusAvailable ? (bool) ($opcacheStatus['restart_pending'] ?? false) : null,
                    'restart_in_progress' => $isOpcacheStatusAvailable ? (bool) ($opcacheStatus['restart_in_progress'] ?? false) : null,
                    'used_memory_bytes' => $isOpcacheStatusAvailable ? ($opcacheStatus['memory_usage']['used_memory'] ?? null) : null,
                    'free_memory_bytes' => $isOpcacheStatusAvailable ? ($opcacheStatus['memory_usage']['free_memory'] ?? null) : null,
                    'wasted_memory_bytes' => $isOpcacheStatusAvailable ? ($opcacheStatus['memory_usage']['wasted_memory'] ?? null) : null,
                    'cached_scripts' => $isOpcacheStatusAvailable ? ($opcacheStatus['opcache_statistics']['num_cached_scripts'] ?? null) : null,
                    'max_cached_keys' => $isOpcacheStatusAvailable ? ($opcacheStatus['opcache_statistics']['max_cached_keys'] ?? null) : null,
                    'hits' => $isOpcacheStatusAvailable ? ($opcacheStatus['opcache_statistics']['hits'] ?? null) : null,
                    'misses' => $isOpcacheStatusAvailable ? ($opcacheStatus['opcache_statistics']['misses'] ?? null) : null,
                    'jit' => [
                        'ini_mode' => (string) ini_get('opcache.jit'),
                        'ini_buffer_size' => (string) ini_get('opcache.jit_buffer_size'),
                        'enabled_runtime' => $jitRuntime !== null ? (bool) ($jitRuntime['enabled'] ?? false) : false,
                        'on_runtime' => $jitRuntime !== null ? (bool) ($jitRuntime['on'] ?? false) : false,
                        'kind' => $jitRuntime['kind'] ?? null,
                        'opt_level' => $jitRuntime['opt_level'] ?? null,
                        'opt_flags' => $jitRuntime['opt_flags'] ?? null,
                        'buffer_size' => $jitRuntime['buffer_size'] ?? null,
                        'buffer_free' => $jitRuntime['buffer_free'] ?? null,
                    ],
                ],
                'extensions' => [
                    'imagick' => extension_loaded('imagick'),
                    'redis' => extension_loaded('redis'),
                    'swoole' => extension_loaded('swoole'),
                ],
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        });

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
