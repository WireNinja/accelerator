<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use WireNinja\Accelerator\Actions\Ticket\ArchiveTicketAction;
use WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketTypeEnum;
use WireNinja\Accelerator\Model\Ticket;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->emptyStateIcon('lucide-ticket')
            ->emptyStateHeading('Belum ada tiket')
            ->emptyStateDescription('Anda bisa membuat tiket baru untuk pertanyaan, bantuan, atau permintaan fitur client.')
            ->emptyStateActions([
                CreateAction::make(),
            ])
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('No. Tiket')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('board.name')
                    ->label('Board')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('boardColumn.name')
                    ->label('Kolom')
                    ->badge()
                    ->color('info'),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (TicketTypeEnum $state): string => $state->getLabel()),
                TextColumn::make('priority')
                    ->label('Prioritas')
                    ->badge()
                    ->color(fn (TicketPriorityEnum $state): string => match ($state) {
                        TicketPriorityEnum::Urgent => 'danger',
                        TicketPriorityEnum::High => 'warning',
                        TicketPriorityEnum::Medium => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (TicketStatusEnum $state): string => $state->getLabel()),
                TextColumn::make('comments_count')
                    ->label('Percakapan')
                    ->counts('comments')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('reporterUser.name')
                    ->label('Pengaju')
                    ->searchable(),
                TextColumn::make('assigneeUser.name')
                    ->label('Penanggung Jawab')
                    ->placeholder('Belum ditugaskan')
                    ->searchable(),
                TextColumn::make('last_activity_at')
                    ->label('Aktivitas Terakhir')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('due_at')
                    ->label('Tenggat')
                    ->since()
                    ->sortable()
                    ->toggleable()
                    ->color(fn (Ticket $record): string => filled($record->due_at) && $record->due_at->isPast()
                        && ! in_array($record->status, [TicketStatusEnum::Resolved, TicketStatusEnum::Closed])
                        ? 'danger'
                        : 'gray'),
                TextColumn::make('access_scope')
                    ->label('Cakupan')
                    ->state(function (Ticket $record): string {
                        $authUserId = (int) mustUser()->id;

                        if ((int) $record->reporter_id === $authUserId && (int) $record->assignee_id === $authUserId) {
                            return 'Milik Saya + Ditugaskan';
                        }

                        if ((int) $record->reporter_id === $authUserId) {
                            return 'Milik Saya';
                        }

                        if ((int) $record->assignee_id === $authUserId) {
                            return 'Ditugaskan ke Saya';
                        }

                        return 'Global/Petugas';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Milik Saya' => 'success',
                        'Ditugaskan ke Saya' => 'info',
                        'Milik Saya + Ditugaskan' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('ticket_board_id')
                    ->label('Board')
                    ->relationship('board', 'name')
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TicketStatusEnum::options())
                    ->multiple(),
                SelectFilter::make('priority')
                    ->label('Prioritas')
                    ->options(TicketPriorityEnum::options())
                    ->multiple(),
                SelectFilter::make('assignee_id')
                    ->label('Penanggung Jawab')
                    ->relationship('assigneeUser', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('overdue')
                    ->label('Melewati Tenggat')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now())
                        ->whereNotIn('status', [TicketStatusEnum::Resolved, TicketStatusEnum::Closed])),
                Filter::make('unresponded')
                    ->label('Belum Direspons (>24j)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('first_response_at')
                        ->whereNotIn('status', [TicketStatusEnum::Resolved, TicketStatusEnum::Closed])
                        ->where('created_at', '<', now()->subHours(24))),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('archive')
                        ->label('Arsipkan')
                        ->icon('lucide-archive')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Ticket $record): void {
                            app(ArchiveTicketAction::class)->handle($record, mustUser());
                        })
                        ->authorize('delete'),
                ]),
            ]);
    }
}
