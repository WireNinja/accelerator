<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Pages;

use Filament\Resources\Pages\CreateRecord;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;

class CreateTicketBoard extends CreateRecord
{
    protected static string $resource = TicketBoardResource::class;
}
