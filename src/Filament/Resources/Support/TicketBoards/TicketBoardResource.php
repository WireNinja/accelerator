<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\TicketBoards;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Override;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Pages\CreateTicketBoard;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Pages\EditTicketBoard;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Pages\ListTicketBoards;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Schemas\TicketBoardForm;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Tables\TicketBoardsTable;
use WireNinja\Accelerator\Filament\Traits\AutoBadge;
use WireNinja\Accelerator\Filament\Traits\BetterResource;
use WireNinja\Accelerator\Model\TicketBoard;

class TicketBoardResource extends Resource
{
    use AutoBadge;
    use BetterResource;

    protected static ?string $model = TicketBoard::class;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return TicketBoardForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return TicketBoardsTable::configure($table);
    }

    /**
     * @return array{}
     */
    #[Override]
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, PageRegistration>
     */
    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListTicketBoards::route('/'),
            'create' => CreateTicketBoard::route('/create'),
            'edit' => EditTicketBoard::route('/{record}/edit'),
        ];
    }
}
