<?php

declare(strict_types=1);

if (! function_exists('accelerator_setting_path')) {
    function accelerator_setting_path(): string
    {
        return __DIR__.'/../Settings';
    }
}

if (! function_exists('accelerator_setting_migration_path')) {
    function accelerator_setting_migration_path(): string
    {
        return __DIR__.'/../../database/settings';
    }
}
