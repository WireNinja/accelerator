<?php

namespace WireNinja\Accelerator\Filament\Tables\Actions;

use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Size;

class BetterActionGroup extends ActionGroup
{
    public static function make(array $actions): static
    {
        return parent::make($actions)
            ->button()
            ->color('primary')
            ->size(Size::Small)
            ->label('Aksi');
    }
}
