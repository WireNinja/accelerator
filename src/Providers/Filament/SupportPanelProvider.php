<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use WireNinja\Accelerator\Filament\Pages\TicketingPage;
use WireNinja\Accelerator\Filament\PanelPreset;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;

class SupportPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = PanelPreset::configure($panel, 'support');

        if (config('accelerator.features.ticketing')) {
            $panel
                ->resources([
                    TicketResource::class,
                    TicketBoardResource::class,
                ])
                ->pages([TicketingPage::class]);
        }

        return $panel;
    }
}
