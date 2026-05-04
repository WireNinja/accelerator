<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Forms\Components;

use CodeWithDennis\FilamentAdvancedChoice\Filament\Forms\Components\Concerns\HasCursorPointer;
use CodeWithDennis\FilamentAdvancedChoice\Filament\Forms\Components\Concerns\HasExtras;
use CodeWithDennis\FilamentAdvancedChoice\Filament\Forms\Components\Concerns\HasHiddenInputs;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Concerns\HasGridDirection;
use Filament\Support\Enums\GridDirection;

class AdvancedCheckboxCards extends CheckboxList
{
    use HasCursorPointer;
    use HasExtras;
    use HasGridDirection;
    use HasHiddenInputs;

    protected string $view = 'accelerator::forms.components.advanced-checkbox-cards';

    protected function setUp(): void
    {
        parent::setUp();

        $this->columns(3)
            ->gridDirection(GridDirection::Row);
    }
}
