<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Livewire\Synthesizers;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

final class BigDecimalSynth extends Synth
{
    /**
     * Key unik untuk mengidentifikasi tipe data ini di metadata Livewire (JSON).
     */
    public static string $key = 'bigdecimal';

    /**
     * Beritahu Livewire object apa yang ditangani synth ini.
     */
    // @phpstan-ignore-next-line
    public static function match($target): bool
    {
        return $target instanceof BigDecimal;
    }

    /**
     * SERVER -> BROWSER
     * Mengubah BigDecimal menjadi format yang bisa dikirim ke JS (String).
     *
     * @param  BigDecimal  $target
     * @param  mixed  $dehydrate
     */
    // @phpstan-ignore-next-line
    public function dehydrate($target, $dehydrate): array
    {
        // Kita kirim sebagai string agar presisi tidak hilang di JavaScript
        return [(string) $target->__toString(), []];
    }

    /**
     * BROWSER -> SERVER
     * Mengubah input dari form (String/Number) kembali menjadi BigDecimal.
     */
    // @phpstan-ignore-next-line
    public function hydrate($value, $meta, $hydrate): ?BigDecimal
    {
        // Handle jika input dikosongkan (empty string atau null)
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $value = (string) $value;

            // Ubah string dari browser kembali ke object BigDecimal
            return BigDecimal::of($value);
        } catch (MathException) {
            // Jika user mengetik karakter aneh (misal: "abc"), return null.
            // Biarkan Validation Rule Laravel (misal: 'numeric') yang menangani errornya nanti.
            return null;
        }
    }
}
