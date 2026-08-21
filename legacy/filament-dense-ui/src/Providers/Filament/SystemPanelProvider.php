<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\PanelProvider;
use WireNinja\Accelerator\Filament\Pages\ManageSystemSettings;
use WireNinja\Accelerator\Filament\PanelPreset;

class SystemPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = PanelPreset::configure($panel, 'system')
            ->plugin(
                FilamentShieldPlugin::make()
                    ->navigationGroup('System'),
            );

        $panel->pages([ManageSystemSettings::class]);

        return $panel;
    }
}
