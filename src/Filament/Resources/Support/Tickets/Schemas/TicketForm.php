<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketTypeEnum;
use WireNinja\Accelerator\Model\TicketBoard;
use WireNinja\Accelerator\Model\TicketBoardColumn;
use WireNinja\Accelerator\Support\Ticket\TicketVisibility;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        $canManageRouting = fn (): bool => TicketVisibility::canManageRouting(mustUser());

        return $schema
            ->components([
                Tabs::make('Detail Tiket')
                    ->tabs([
                        Tab::make('Informasi Utama')
                            ->icon('lucide-ticket')
                            ->components([
                                Grid::make(12)
                                    ->components([
                                        Section::make('Detail Tiket')
                                            ->icon('lucide-message-square-text')
                                            ->columnSpan(8)
                                            ->columns(2)
                                            ->components([
                                                Callout::make('Visibilitas Tiket')
                                                    ->icon('lucide-shield-alert')
                                                    ->warning()
                                                    ->description(fn (): string => TicketVisibility::describeAccess(mustUser()))
                                                    ->columnSpanFull(),
                                                TextInput::make('title')
                                                    ->label('Judul Permintaan')
                                                    ->placeholder('Contoh: Butuh fitur export PDF')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                Textarea::make('description')
                                                    ->label('Keterangan')
                                                    ->placeholder('Jelaskan kebutuhan, kendala, atau pertanyaan client secara rinci.')
                                                    ->rows(6)
                                                    ->columnSpanFull(),
                                                Select::make('type')
                                                    ->label('Jenis Permintaan')
                                                    ->options(TicketTypeEnum::options())
                                                    ->required()
                                                    ->default(TicketTypeEnum::Question->value),
                                                Select::make('priority')
                                                    ->label('Prioritas')
                                                    ->options(TicketPriorityEnum::options())
                                                    ->required()
                                                    ->default(TicketPriorityEnum::Medium->value),
                                            ]),
                                        Section::make('Operasional')
                                            ->icon('lucide-workflow')
                                            ->columnSpan(4)
                                            ->components([
                                                Callout::make('Routing Otomatis')
                                                    ->icon('lucide-info')
                                                    ->info()
                                                    ->description('Untuk client, board/kolom/status ditentukan otomatis oleh sistem. Petugas bisa mengatur manual kalau perlu.')
                                                    ->visible(fn (): bool => ! $canManageRouting()),
                                                Select::make('ticket_board_id')
                                                    ->label('Board')
                                                    ->relationship('board', 'name')
                                                    ->required(fn (): bool => $canManageRouting())
                                                    ->live()
                                                    ->visible(fn (): bool => $canManageRouting()),
                                                Hidden::make('ticket_board_id')
                                                    ->default(function (): ?int {
                                                        $boardIdFromQuery = request()->integer('ticket_board_id');

                                                        if ($boardIdFromQuery > 0) {
                                                            return $boardIdFromQuery;
                                                        }

                                                        return TicketBoard::query()->where('is_default', true)->value('id')
                                                            ?? TicketBoard::query()->value('id');
                                                    })
                                                    ->dehydrated(fn (): bool => ! $canManageRouting())
                                                    ->visible(fn (): bool => ! $canManageRouting()),
                                                Select::make('ticket_board_column_id')
                                                    ->label('Kolom Board')
                                                    ->options(fn (Get $get): array => TicketBoardColumn::query()
                                                        ->where('ticket_board_id', $get('ticket_board_id'))
                                                        ->orderBy('position')
                                                        ->pluck('name', 'id')
                                                        ->all())
                                                    ->required(fn (): bool => $canManageRouting())
                                                    ->visible(fn (): bool => $canManageRouting()),
                                                Select::make('reporter_id')
                                                    ->label('Pelapor')
                                                    ->relationship('reporterUser', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(fn (): bool => $canManageRouting())
                                                    ->default(fn (): int => mustUser()->id)
                                                    ->visible(fn (): bool => $canManageRouting()),
                                                Hidden::make('reporter_id')
                                                    ->default(fn (): int => mustUser()->id)
                                                    ->dehydrated(fn (): bool => ! $canManageRouting())
                                                    ->visible(fn (): bool => ! $canManageRouting()),
                                                Select::make('assignee_id')
                                                    ->label('Ditugaskan Ke')
                                                    ->relationship('assigneeUser', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->helperText('Isi jika tiket sudah punya penanggung jawab.')
                                                    ->visible(fn (): bool => $canManageRouting()),
                                                Select::make('status')
                                                    ->label('Status')
                                                    ->options(TicketStatusEnum::options())
                                                    ->required(fn (): bool => $canManageRouting())
                                                    ->default(TicketStatusEnum::Open->value)
                                                    ->visible(fn (): bool => $canManageRouting()),
                                                Hidden::make('status')
                                                    ->default(TicketStatusEnum::Open->value)
                                                    ->dehydrated(fn (): bool => ! $canManageRouting())
                                                    ->visible(fn (): bool => ! $canManageRouting()),
                                                TextInput::make('effort_points')
                                                    ->label('Perkiraan Kerja')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->visible(fn (): bool => $canManageRouting()),
                                                DateTimePicker::make('due_at')
                                                    ->label('Target Waktu')
                                                    ->visible(fn (): bool => $canManageRouting()),
                                                Select::make('watchers')
                                                    ->label('CC / Pengamat')
                                                    ->relationship('watchers', 'name')
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload()
                                                    ->helperText('Orang tambahan yang bisa melihat tiket ini.'),
                                                Toggle::make('is_public')
                                                    ->label('Terlihat Semua Staff')
                                                    ->helperText('Jika aktif, semua staff perusahaan bisa melihat tiket ini.')
                                                    ->default(false),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Work Log')
                            ->icon('lucide-briefcase-business')
                            ->visible(fn (): bool => $canManageRouting())
                            ->components([
                                Section::make('Catatan Kerja')
                                    ->description('Catat effort nyata supaya tiket ini punya jejak waktu yang jelas.')
                                    ->columnSpanFull()
                                    ->components([
                                        Repeater::make('workLogs')
                                            ->label('Log Waktu')
                                            ->relationship()
                                            ->columnSpanFull()
                                            ->schema([
                                                TextInput::make('minutes_spent')
                                                    ->label('Durasi (Menit)')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->required(),
                                                DateTimePicker::make('logged_at')
                                                    ->label('Dicatat Pada')
                                                    ->required(),
                                                Textarea::make('notes')
                                                    ->label('Catatan')
                                                    ->rows(3)
                                                    ->required()
                                                    ->columnSpanFull(),
                                                Hidden::make('user_id')
                                                    ->default(fn (): int => mustUser()->id),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Field Tambahan')
                            ->icon('lucide-sliders-horizontal')
                            ->components([
                                Select::make('modul_terkait')
                                    ->label('Modul Terkait')
                                    ->options([
                                        'inventory' => 'Inventory',
                                        'finance' => 'Finance',
                                        'hr' => 'HR',
                                        'sales' => 'Sales',
                                        'purchasing' => 'Purchasing',
                                    ]),
                                Select::make('dampak_bisnis')
                                    ->label('Dampak Bisnis')
                                    ->options([
                                        'rendah' => 'Rendah',
                                        'sedang' => 'Sedang',
                                        'tinggi' => 'Tinggi',
                                        'kritis' => 'Kritis',
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
