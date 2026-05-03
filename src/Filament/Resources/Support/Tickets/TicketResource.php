<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\CreateTicket;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\EditTicket;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\ListTickets;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\ViewTicket;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers\TicketAttachmentsRelationManager;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers\TicketCommentsRelationManager;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers\TicketRelationsRelationManager;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Schemas\TicketForm;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Tables\TicketsTable;
use WireNinja\Accelerator\Filament\Traits\AutoBadge;
use WireNinja\Accelerator\Filament\Traits\BetterResource;
use WireNinja\Accelerator\Model\Ticket;

class TicketResource extends Resource
{
    use AutoBadge;
    use BetterResource;

    protected static ?string $model = Ticket::class;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    /**
     * @return array{}
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            TicketCommentsRelationManager::class,
            TicketAttachmentsRelationManager::class,
            TicketRelationsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->visibleTo(mustUser()); // @phpstan-ignore method.notFound
    }
}
