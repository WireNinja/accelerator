<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;
use WireNinja\Accelerator\Model\TicketComment;
use WireNinja\Accelerator\Support\Ticket\TicketVisibility;

class TicketCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $recordTitleAttribute = 'body';

    public function form(Schema $schema): Schema
    {
        $isStaff = TicketVisibility::canManageRouting(mustUser());

        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Pesan')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('is_internal')
                    ->label('Catatan Internal')
                    ->helperText('Catatan internal hanya terlihat oleh petugas, tidak oleh client.')
                    ->visible($isStaff)
                    ->default(false),
            ]);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')
                    ->label('Pesan')
                    ->limit(80)
                    ->wrap(),
                IconColumn::make('is_internal')
                    ->label('Internal')
                    ->boolean()
                    ->trueIcon('lucide-lock')
                    ->falseIcon('lucide-globe')
                    ->trueColor('gray')
                    ->falseColor('info'),
                TextColumn::make('user.name')
                    ->label('Pengirim')
                    ->placeholder('Sistem'),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('j F Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = mustUser()->id;

                        return $data;
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->authorize(fn (TicketComment $record): bool => $record->user_id === mustUser()->id || mustUser()->can('manage-tickets')),
            ])
            ->emptyStateHeading('Belum ada percakapan')
            ->emptyStateDescription('Tambahkan pesan atau catatan internal terkait tiket ini.');
    }
}
