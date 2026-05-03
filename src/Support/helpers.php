<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

if (! function_exists('user')) {
    /**
     * Get the currently authenticated user.
     */
    function user(): ?User
    {
        return Auth::user();
    }
}

if (! function_exists('mustUser')) {
    /**
     * Get the currently authenticated user or throw an exception if not logged in.
     *
     * @throws UnauthorizedHttpException
     */
    function mustUser(): User
    {
        $user = Auth::user();

        if (! $user) {
            throw new UnauthorizedHttpException('AcceleratedSession', 'User is not authenticated.');
        }

        return $user;
    }
}

if (! function_exists('accelerator_setting_path')) {
    function accelerator_setting_path(): string
    {
        return realpath(__DIR__.'/../Settings');
    }
}

if (! function_exists('accelerator_setting_migration_path')) {
    function accelerator_setting_migration_path(): string
    {
        return realpath(__DIR__.'/../../database/settings');
    }
}
