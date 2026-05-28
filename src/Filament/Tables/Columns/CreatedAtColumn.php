<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Tables\Columns;

class CreatedAtColumn extends TimestampSummaryColumn
{
    public static function getDefaultName(): ?string
    {
        return 'created_at';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Dibuat Pada')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
