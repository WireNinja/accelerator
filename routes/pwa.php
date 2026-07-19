<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (app()->isLocal() || app()->hasDebugModeEnabled()) {
    Route::middleware('web')->get('/build/sw.js', static fn () => response()->file(
        public_path('sw.js'),
        [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Content-Type' => 'application/javascript',
            'Expires' => '0',
            'Pragma' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ],
    ));
}
