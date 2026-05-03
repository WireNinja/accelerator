<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TicketBoardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Detail Board Tiket')
                    ->tabs([
                        Tab::make('Informasi Utama')
                            ->icon('lucide-layout-grid')
                            ->components([
                                Grid::make(12)
                                    ->components([
                                        Section::make('Konfigurasi Board')
                                            ->icon('lucide-settings-2')
                                            ->columnSpan(6)
                                            ->components([
                                                Hidden::make('uuid')
                                                    ->default(fn (): string => (string) Str::uuid()),
                                                TextInput::make('name')
                                                    ->label('Nama Board')
                                                    ->placeholder('Contoh: Support Client')
                                                    ->required()
                                                    ->maxLength(255),
                                                Textarea::make('description')
                                                    ->label('Deskripsi')
                                                    ->placeholder('Deskripsi singkat tujuan board ini')
                                                    ->rows(3),
                                                Toggle::make('is_default')
                                                    ->label('Jadikan Board Default')
                                                    ->helperText('Ticketing page akan menampilkan board default terlebih dahulu.')
                                                    ->default(false),
                                            ]),
                                        Section::make('Kolom Workflow')
                                            ->icon('lucide-columns-3')
                                            ->columnSpan(6)
                                            ->components([
                                                Repeater::make('columns')
                                                    ->label('Daftar Kolom')
                                                    ->relationship('columns')
                                                    ->collapsible()
                                                    ->cloneable()
                                                    ->defaultItems(0)
                                                    ->reorderableWithButtons()
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label('Nama Kolom')
                                                            ->placeholder('Contoh: In Progress, Review, Done')
                                                            ->required()
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                                                if (filled($get('slug'))) {
                                                                    return;
                                                                }

                                                                $set('slug', Str::slug((string) $state));
                                                            })
                                                            ->maxLength(255),
                                                        TextInput::make('slug')
                                                            ->label('Slug')
                                                            ->helperText('Otomatis dibuat dari nama kolom. Bisa diubah manual jika diperlukan.')
                                                            ->required()
                                                            ->maxLength(255),
                                                        TextInput::make('position')
                                                            ->label('Posisi')
                                                            ->helperText('Urutan kolom di board, dimulai dari 1.')
                                                            ->required()
                                                            ->numeric()
                                                            ->default(1),
                                                        TextInput::make('wip_limit')
                                                            ->label('Batas WIP')
                                                            ->helperText('Jumlah maksimum tiket yang boleh berada di kolom ini secara bersamaan. Kosongkan jika tidak dibatasi.')
                                                            ->numeric()
                                                            ->nullable(),
                                                        Toggle::make('is_done')
                                                            ->label('Kolom Selesai')
                                                            ->helperText('Tandai jika kolom ini menandakan tiket sudah selesai dikerjakan.')
                                                            ->default(false),
                                                    ])
                                                    ->columns(2)
                                                    ->grid(1)
                                                    ->addActionLabel('Tambah Kolom Workflow'),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
