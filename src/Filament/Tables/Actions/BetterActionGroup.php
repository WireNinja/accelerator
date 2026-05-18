<?php

namespace WireNinja\Accelerator\Filament\Tables\Actions;

use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Size;

class BetterActionGroup extends ActionGroup
{
    /**
     * @DONOT-REMOVE label('Aksi')
     * Default label sengaja Bahasa Indonesia karena project ini opinionated id-ID.
     * Caller tetap bisa override per-instance dengan ->label('Custom') setelah
     * BetterActionGroup::make() — chain method label() dipanggil paling akhir akan menang.
     * Jangan refactor jadi translatable string tanpa konfirmasi user; helper ini sengaja
     * hardcoded supaya tidak butuh publish lang file untuk pakai komponen.
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
