<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages;

use Filament\Resources\Pages\CreateRecord;
use Override;
use WireNinja\Accelerator\Actions\Ticket\PrepareTicketForCreateAction;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(PrepareTicketForCreateAction::class)->handle($data);
    }
}
