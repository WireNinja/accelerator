<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use WireNinja\Accelerator\Http\Middleware\HandleAppearance;
use WireNinja\Accelerator\Http\Middleware\HandleInertiaRequests;

final class BuiltinMiddleware
{
    public static function make(Middleware $middleware): void
    {
        $middleware->remove([
            PreventRequestsDuringMaintenance::class,
            CheckForMaintenanceMode::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    }
}
