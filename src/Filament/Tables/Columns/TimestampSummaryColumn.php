<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column;

class TimestampSummaryColumn extends Column
{
    protected string $view = 'accelerator::filament.tables.columns.timestamp-summary-column';

    protected string|Closure|null $emptyLabel = null;

    public function emptyLabel(string|Closure|null $label): static
    {
        $this->emptyLabel = $label;

        return $this;
    }

    public function getEmptyLabel(): string
    {
        return $this->evaluate($this->emptyLabel) ?? 'Belum tersedia';
    }
}
