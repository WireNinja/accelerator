<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WireNinja\Accelerator\Http\Controllers\Auth\GoogleAuthController;

Route::middleware('web')->group(function (): void {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
