<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Components;

use Brick\Math\BigDecimal;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

final class SeparatedNumberInput
{
    public static function make(
        ?string $name = null,
        int $precision = 0,
        string $decimalSeparator = '.', // Default Indo: Koma untuk desimal
        string $thousandsSeparator = ',' // Default Indo: Titik untuk ribuan
    ): TextInput {
        $mask = sprintf(
            '$money($input, \'%s\', \'%s\', %d)',
            addslashes($decimalSeparator),
            addslashes($thousandsSeparator),
            $precision
        );

        return TextInput::make($name)
            ->mask(RawJs::make($mask))
            ->stripCharacters($thousandsSeparator) // Hapus pemisah ribuan sebelum kirim ke server
            // ->numeric() // <--- HAPUS ATAU COMMENT BARIS INI (Penyebab Error)

            // Gantinya, kita format manual state-nya agar BigDecimal jadi String
            ->formatStateUsing(
                fn ($state) => $state instanceof BigDecimal ? $state->__toString() : $state
            )

            // Tambahkan validasi manual karena ->numeric() dihapus
            ->rules(['numeric'])
            ->rule('decimal:0,'.$precision)

            ->step(self::precisionToStep($precision))
            ->default(0);
    }

    private static function precisionToStep(int $precision): string
    {
        return $precision <= 0
            ? '1'
            : '0.'.str_repeat('0', $precision - 1).'1';
    }
}
