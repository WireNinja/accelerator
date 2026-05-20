<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Livewire\Synthesizers;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

final class BigDecimalSynth extends Synth
{
    /**
     * Unique key to identify this data type in Livewire metadata (JSON).
     */
    public static string $key = 'bigdecimal';

    /**
     * Tell Livewire which object this synth handles.
     */
    // @phpstan-ignore-next-line
    public static function match($target): bool
    {
        return $target instanceof BigDecimal;
    }

    /**
     * SERVER -> BROWSER
     * Convert BigDecimal to a format that can be sent to JS (String).
     *
     * @param  BigDecimal  $target
     * @param  mixed  $dehydrate
     */
    // @phpstan-ignore-next-line
    public function dehydrate($target, $dehydrate): array
    {
        // Send as string so precision is not lost in JavaScript
        return [(string) $target->__toString(), []];
    }

    /**
     * BROWSER -> SERVER
     * Convert form input (String/Number) back to BigDecimal.
     */
    // @phpstan-ignore-next-line
    public function hydrate($value, $meta, $hydrate): ?BigDecimal
    {
        // Handle empty input (empty string or null)
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $value = (string) $value;

            // Convert string from browser back to BigDecimal object
            return BigDecimal::of($value);
        } catch (MathException) {
            // If user types invalid characters (e.g. "abc"), return null.
            // Let Laravel Validation Rules (e.g. 'numeric') handle the error.
            return null;
        }
    }
}
