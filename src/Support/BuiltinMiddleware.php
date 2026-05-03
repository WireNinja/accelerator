<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

final class BuiltinMiddleware
{
    public static function make(Middleware $middleware): void
    {
        $middleware->remove([
            PreventRequestsDuringMaintenance::class,
        ]);

        // $middleware->web(append: [
        //     // SmartTelemetrySampler::class,
        // ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    }
}
