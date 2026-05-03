<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;
use WireNinja\Accelerator\Enums\Ticket\TicketRelationTypeEnum;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;
use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Model\TicketRelation;

class TicketRelationsRelationManager extends RelationManager
{
    protected static string $relationship = 'outgoingRelations';

    protected static ?string $recordTitleAttribute = 'relation_type';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('related_ticket_id')
                    ->label('Tiket Terkait')
                    ->options(fn (): array => Ticket::query()
                        ->withoutGlobalScopes()
                        ->select(['id', 'ticket_number', 'title'])
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (Ticket $ticket): array => [$ticket->id => "[{$ticket->ticket_number}] {$ticket->title}"])
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('relation_type')
                    ->label('Jenis Relasi')
                    ->options(TicketRelationTypeEnum::options())
                    ->required()
                    ->default(TicketRelationTypeEnum::RelatedTo->value),
            ]);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('relatedTicket.ticket_number')
                    ->label('Tiket Terkait')
                    ->description(fn (TicketRelation $record): string => $record->relatedTicket?->title ?? '')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('relation_type')
                    ->label('Jenis Relasi')
                    ->badge()
                    ->formatStateUsing(fn (TicketRelationTypeEnum $state): string => $state->getLabel())
                    ->color(fn (TicketRelationTypeEnum $state): string => $state->getColor()),
                TextColumn::make('createdByUser.name')
                    ->label('Ditambahkan Oleh')
                    ->placeholder('Sistem'),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('j F Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Buka Tiket')
                    ->icon('lucide-external-link')
                    ->url(fn (TicketRelation $record): string => TicketResource::getUrl('edit', ['record' => $record->related_ticket_id]))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Belum ada relasi tiket')
            ->emptyStateDescription('Tambahkan relasi jika tiket ini duplikat, diblokir, atau berkaitan dengan tiket lain.');
    }
}
