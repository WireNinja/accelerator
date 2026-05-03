<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;

class ListTicketBoards extends ListRecords
{
    protected static string $resource = TicketBoardResource::class;

    /**
     * @return CreateAction[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
