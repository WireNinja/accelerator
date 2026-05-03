<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;

class EditTicketBoard extends EditRecord
{
    protected static string $resource = TicketBoardResource::class;

    /**
     * @return DeleteAction[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
