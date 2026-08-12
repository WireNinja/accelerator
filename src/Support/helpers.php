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
