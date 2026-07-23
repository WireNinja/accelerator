<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WireNinja\Accelerator\Http\Controllers\Insider\InsiderController;

Route::middleware(['web', 'auth', 'role:super_admin'])
    ->prefix('insider')
    ->name('accelerator.insider.')
    ->get('/', InsiderController::class)
    ->name('index');
