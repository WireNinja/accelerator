<?php

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

if (! function_exists('is_swoole_runtime')) {
    /**
     * Check if the current PHP process is an Octane Swoole worker.
     */
    function is_swoole_runtime(): bool
    {
        return is_octane_runtime() && extension_loaded('swoole');
    }
}

if (! function_exists('is_octane_runtime')) {
    /**
     * Check if the current PHP process is running inside Laravel Octane.
     */
    function is_octane_runtime(): bool
    {
        return ($_SERVER['LARAVEL_OCTANE'] ?? null) === '1';
    }
}
