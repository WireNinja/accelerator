<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Plugins;

use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Panel;
use WireNinja\Accelerator\Filament\Pages\ManageAppSettings;

class BuiltinSettingPlugin implements FilamentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'builtin-setting';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            ManageAppSettings::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
