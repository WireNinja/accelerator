<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use WireNinja\Accelerator\Filament\PanelPreset;
use WireNinja\Accelerator\Filament\Plugins\BuiltinTicketingPlugin;

class SupportPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = PanelPreset::configure($panel, 'support');

        if (config('accelerator.features.ticketing')) {
            $panel->plugin(BuiltinTicketingPlugin::make());
        }

        return $panel;
    }
}
