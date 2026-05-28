<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Tables\Columns;

class UpdatedAtColumn extends TimestampSummaryColumn
{
    public static function getDefaultName(): ?string
    {
        return 'updated_at';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Diperbarui Pada')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
