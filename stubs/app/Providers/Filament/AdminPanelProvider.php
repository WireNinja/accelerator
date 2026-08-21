<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use WireNinja\Accelerator\Filament\PanelPreset;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return PanelPreset::configure($panel, 'admin')
            ->default();
    }
}
