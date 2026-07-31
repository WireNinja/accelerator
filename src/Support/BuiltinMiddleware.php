<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use WireNinja\Accelerator\Http\Middleware\EnsureUserIsActive;
use WireNinja\Accelerator\Http\Middleware\HandleAppearance;

final class BuiltinMiddleware
{
    public static function make(Middleware $middleware): void
    {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    }
}
