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
        return PanelPreset::configure($panel, 'support')
            ->plugins([
                BuiltinTicketingPlugin::make(),
            ]);
    }
}
