<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\PanelProvider;
use WireNinja\Accelerator\Filament\PanelPreset;
use WireNinja\Accelerator\Filament\Plugins\BuiltinSettingPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return PanelPreset::configure($panel, 'admin')
            ->default()
            ->plugins([
                BuiltinSettingPlugin::make(),
                FilamentShieldPlugin::make(),
            ]);
    }
}
