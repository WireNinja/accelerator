<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Components;

use Brick\Math\BigDecimal;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

final class SeparatedNumberInput
{
    /**
     * @DONOT-REMOVE separator default `.` (decimal) and `,` (thousands)
     *
     * These defaults INTENTIONALLY follow US format (5,000.00), NOT id-ID (5.000,00).
     * Although this project is opinionated Indonesian, inverting separators breaks the pipeline:
     *
     *   Browser sends: "5.000,00"
     *   ->stripCharacters('.') -> "5000,00"
     *   BigDecimalSynth::hydrate('5000,00') -> BigDecimal::of() throws MathException
     *   -> rescue to null -> data silently lost.
     *
     * Until mask + stripCharacters + BigDecimal pipeline is made locale-aware,
     * decimal MUST be '.' and thousands MUST be ','. For users, visually it remains
     * "5,000.00" — not ideal for Indonesian locale, but DATA DOES NOT CORRUPT.
     *
     * Do not change these defaults until:
     *   1. BigDecimalSynth::hydrate() can parse comma as decimal, or
     *   2. SeparatedNumberInput adds ->mutateDehydratedStateUsing() that normalizes
     *      `,` -> `.` before casting to BigDecimal.
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
            ->stripCharacters($thousandsSeparator) // Strip thousands separator before sending to server
            // ->numeric() // <--- DO NOT ENABLE (causes Error with BigDecimal pipeline)

            // Instead, manually format state so BigDecimal becomes String
            ->formatStateUsing(
                fn ($state) => $state instanceof BigDecimal ? $state->__toString() : $state
            )

            // Add manual validation since ->numeric() is removed
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
