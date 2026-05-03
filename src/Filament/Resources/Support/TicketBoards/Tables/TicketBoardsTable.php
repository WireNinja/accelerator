<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketBoardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateIcon('lucide-layout-grid')
            ->emptyStateHeading('Belum ada board tiket')
            ->emptyStateDescription('Anda bisa membuat board tiket baru untuk mengelola alur support client.')
            ->emptyStateActions([
                CreateAction::make(),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Board')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(80)
                    ->placeholder('-')
                    ->toggleable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                TextColumn::make('columns_count')
                    ->label('Jumlah Kolom')
                    ->counts('columns')
                    ->badge()
                    ->color('info'),
                TextColumn::make('tickets_count')
                    ->label('Jumlah Tiket')
                    ->counts('tickets')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
