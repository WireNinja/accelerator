<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\RelationManagers;

use App\Models\DocumentAttachment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $recordTitleAttribute = 'original_name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Berkas')
                    ->disk('local')
                    ->directory('ticket-attachments')
                    ->required()
                    ->visibility('private')
                    ->storeFileNamesIn('original_name')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                        'application/zip',
                    ])
                    ->maxSize(10240),
                Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'screenshot' => 'Screenshot',
                        'file' => 'File',
                        'proof' => 'Bukti',
                        'other' => 'Lainnya',
                    ])
                    ->required()
                    ->default('screenshot'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->columnSpanFull(),
                Hidden::make('uploaded_by')
                    ->default(fn (): int => mustUser()->id),
                Hidden::make('uploaded_at')
                    ->default(fn () => now()),
            ]);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label('Preview')
                    ->disk('local')
                    ->imageSize(40)
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-file.svg'))
                    ->visible(fn (): bool => true)
                    ->checkFileExistence(false),
                TextColumn::make('original_name')
                    ->label('Nama File')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'screenshot' => 'Screenshot',
                        'file' => 'File',
                        'proof' => 'Bukti',
                        default => 'Lainnya',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'screenshot' => 'info',
                        'file' => 'gray',
                        'proof' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('Tidak ada catatan'),
                TextColumn::make('uploadedByUser.name')
                    ->label('Diunggah Oleh')
                    ->placeholder('Sistem'),
                TextColumn::make('uploaded_at')
                    ->label('Waktu Upload')
                    ->dateTime('j F Y H:i'),
                TextColumn::make('size_bytes')
                    ->label('Ukuran')
                    ->formatStateUsing(fn (?int $state): string => $this->formatFileSize($state)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['disk'] = 'local';

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Unduh')
                    ->icon('lucide-download')
                    ->action(function (DocumentAttachment $record): StreamedResponse {
                        $disk = Storage::disk($record->disk ?? 'local');

                        abort_unless($disk->exists($record->path), 404);

                        return $disk->download($record->path, $record->original_name);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Belum ada attachment')
            ->emptyStateDescription('Tambahkan screenshot, file pendukung, atau bukti komunikasi di sini.');
    }

    protected function formatFileSize(?int $bytes): string
    {
        if (blank($bytes)) {
            return '-';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $kilobytes = $bytes / 1024;

        if ($kilobytes < 1024) {
            return number_format($kilobytes, 1).' KB';
        }

        return number_format($kilobytes / 1024, 1).' MB';
    }
}
