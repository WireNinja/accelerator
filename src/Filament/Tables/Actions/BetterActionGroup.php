<?php

namespace WireNinja\Accelerator\Filament\Tables\Actions;

use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Size;

class BetterActionGroup extends ActionGroup
{
    /**
     * @DONOT-REMOVE label('Aksi')
     * Default label is intentionally Indonesian because this project is opinionated id-ID.
     * Callers can still override per-instance with ->label('Custom') after
     * BetterActionGroup::make() — the last chained label() call wins.
     * Do not refactor to translatable strings without user confirmation; this helper is
     * intentionally hardcoded so it does not require publishing lang files to use.
     */
    public static function make(array $actions = []): static
    {
        return parent::make($actions)
            ->button()
            ->color('primary')
            ->size(Size::Small)
            ->label('Aksi');
    }
}
