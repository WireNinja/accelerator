<?php

namespace WireNinja\Accelerator\Filament\Tables\Actions;

use Filament\Support\Enums\Size;
use Filament\Tables\Actions\ActionGroup;

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
