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
    public static function match(mixed $target): bool
    {
        return $target instanceof BigDecimal;
    }

    /**
     * SERVER -> BROWSER
     * Convert BigDecimal to a format that can be sent to JS (String).
     *
     * @return array{string, array{}}
     */
    public function dehydrate(mixed $target, mixed $dehydrate): array
    {
        return [(string) $target, []];
    }

    /**
     * BROWSER -> SERVER
     * Convert form input (String/Number) back to BigDecimal.
     */
    public function hydrate(mixed $value, mixed $meta, mixed $hydrate): ?BigDecimal
    {
        // Handle empty input (empty string or null)
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return BigDecimal::of((string) $value);
        } catch (MathException) {
            // If user types invalid characters (e.g. "abc"), return null.
            // Let Laravel Validation Rules (e.g. 'numeric') handle the error.
            return null;
        }
    }
}
