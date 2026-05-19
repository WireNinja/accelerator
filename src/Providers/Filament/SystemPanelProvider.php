<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\PanelProvider;
use WireNinja\Accelerator\Filament\PanelPreset;
use WireNinja\Accelerator\Filament\Plugins\BuiltinSettingPlugin;

class SystemPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return PanelPreset::configure($panel, 'system')
            ->plugins([
                BuiltinSettingPlugin::make(),
                FilamentShieldPlugin::make()
                    ->navigationGroup('System'),
            ]);
    }
}
