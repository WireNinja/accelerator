<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Components;

use Brick\Math\BigDecimal;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

final class SeparatedNumberInput
{
    /**
     * @DONOT-REMOVE separator default `.` (decimal) dan `,` (thousands)
     *
     * Default ini SECARA SENGAJA mengikuti format US (5,000.00), BUKAN id-ID (5.000,00).
     * Walaupun project ini opinionated Indonesia, invert separator akan memecah pipeline:
     *
     *   Browser kirim: "5.000,00"
     *   ->stripCharacters('.') -> "5000,00"
     *   BigDecimalSynth::hydrate('5000,00') -> BigDecimal::of() throw MathException
     *   -> rescue ke null -> data hilang silent.
     *
     * Selama mask + stripCharacters + BigDecimal pipeline belum dibikin locale-aware,
     * decimal HARUS '.' dan thousands HARUS ','. Bagi user, secara visual tetap
     * "5,000.00" — kurang ideal untuk locale Indo, tapi DATA TIDAK CORRUPT.
     *
     * Jangan ubah default ini sebelum:
     *   1. BigDecimalSynth::hydrate() bisa parse koma decimal, atau
     *   2. SeparatedNumberInput tambah ->mutateDehydratedStateUsing() yang normalize
     *      `,` -> `.` sebelum cast ke BigDecimal.
     */
    public static function make(
        ?string $name = null,
        int $precision = 0,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ','
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
                fn($state) => $state instanceof BigDecimal ? $state->__toString() : $state
            )

            // Tambahkan validasi manual karena ->numeric() dihapus
            ->rules(['numeric'])
            ->rule('decimal:0,' . $precision)

            ->step(self::precisionToStep($precision))
            ->default(0);
    }

    private static function precisionToStep(int $precision): string
    {
        return $precision <= 0
            ? '1'
            : '0.' . str_repeat('0', $precision - 1) . '1';
    }
}
