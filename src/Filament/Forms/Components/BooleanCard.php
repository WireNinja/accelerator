<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Forms\Components;

use CodeWithDennis\FilamentAdvancedChoice\Filament\Forms\Components\CheckboxList;

class BooleanCard extends CheckboxList
{
    protected string $trueLabel = 'Aktif';

    protected ?string $trueDescription = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel()
            ->formatStateUsing(fn($state) => $state ? [true] : [])
            ->dehydrateStateUsing(fn($state) => filled($state))
            ->options(fn() => [true => $this->trueLabel])
            ->descriptions(fn() => $this->trueDescription ? [true => $this->trueDescription] : []);
    }

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function trueDescription(string $description): static
    {
        $this->trueDescription = $description;

        return $this;
    }
}
