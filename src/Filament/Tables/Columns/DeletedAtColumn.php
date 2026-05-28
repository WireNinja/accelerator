<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Tables\Columns;

class DeletedAtColumn extends TimestampSummaryColumn
{
    public static function getDefaultName(): ?string
    {
        return 'deleted_at';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Dihapus Pada')
            ->emptyLabel('Belum dihapus')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
