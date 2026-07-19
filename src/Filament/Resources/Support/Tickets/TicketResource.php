<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use WireNinja\Accelerator\Attributes\DiscoverAsResource;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\CreateTicket;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\EditTicket;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\ListTickets;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages\ViewTicket;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers\TicketAttachmentsRelationManager;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers\TicketCommentsRelationManager;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers\TicketRelationsRelationManager;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Schemas\TicketForm;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Tables\TicketsTable;
use WireNinja\Accelerator\Filament\Traits\BetterResource;
use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Policies\TicketPolicy;
use WireNinja\Accelerator\Support\UserModel;

/**
 * @extends resource<Ticket>
 */
#[DiscoverAsResource(
    key: 'ticket',
    form: TicketForm::class,
    table: TicketsTable::class,
    policy: TicketPolicy::class,
)]
class TicketResource extends Resource
{
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
     * @return array<class-string<RelationManager> | RelationGroup | RelationManagerConfiguration>
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

    /**
     * @return Builder<Ticket>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->visibleTo(UserModel::current());
    }
}
