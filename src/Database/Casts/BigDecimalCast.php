<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Database\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<BigDecimal|null, string|int|float|BigDecimal|null>
 */
class BigDecimalCast implements CastsAttributes
{
    private readonly ?int $scale;

    private RoundingMode $roundingMode;

    /**
     * Constructor menerima parameter dari definisi cast di Model.
     * Contoh: BigDecimalCast::class . ':2,DOWN'
     * Laravel akan mengirim parameter sebagai string.
     */
    public function __construct(
        string|int|null $scale = null,
        string|RoundingMode $roundingMode = RoundingMode::HalfUp,
    ) {
        $this->scale = $scale !== null && $scale !== '' ? (int) $scale : null;

        /*
    |--------------------------------------------------------------------------
    | Konversi String → Unit Enum RoundingMode (Safe dengan Fallback)
    |--------------------------------------------------------------------------
    |
    | Jika string yang dikirim tidak valid (typo di definisi cast Model),
    | kita TIDAK mau crash seluruh request — tapi kita HARUS tahu kejadiannya.
    |
    | rescue() menjamin dua hal sekaligus:
    |   1. Exception tetap dilaporkan ke Laravel Nightwatch / log
    |   2. Fallback ke HalfUp agar aplikasi tetap berjalan
    |
    | Ini lebih baik dari throw langsung di constructor karena Cast dipanggil
    | saat Model di-instantiate — crash di sini bisa mematikan banyak hal.
    |
    */
        if ($roundingMode instanceof RoundingMode) {
            $this->roundingMode = $roundingMode;
        } else {
            $cases = RoundingMode::cases();
            $lookup = [];
            foreach ($cases as $case) {
                $lookup[strtoupper($case->name)] = $case;
            }
            $normalized = strtoupper((string) $roundingMode);

            // @TODO : fix this
            $this->roundingMode = rescue(
                fn () => $lookup[$normalized] ?? throw new InvalidArgumentException(
                    sprintf(
                        'RoundingMode "%s" tidak valid. Pilihan yang tersedia: %s.',
                        $roundingMode,
                        implode(', ', array_column($cases, 'name')),
                    )
                ),
                RoundingMode::HalfUp,
            );
        }
    }

    /**
     * Helper static untuk mempermudah penulisan di Model.
     * Penggunaan: BigDecimalCast::scale(2, RoundingMode::DOWN)
     */
    public static function scale(int $scale, RoundingMode $roundingMode = RoundingMode::HalfUp): string
    {
        return self::class.sprintf(':%d,%s', $scale, $roundingMode->name);
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        if ($value === null) {
            return null;
        }

        /*
    |--------------------------------------------------------------------------
    | Cast dari DB → BigDecimal
    |--------------------------------------------------------------------------
    |
    | Tidak boleh silent fail. Jika data di DB korup, kita HARUS tahu.
    | Kembalikan zero diam-diam = data keuangan corrupt tanpa jejak.
    |
    */
        $bigDecimal = BigDecimal::of(strval($value));

        if ($this->scale !== null) {
            return $bigDecimal->toScale($this->scale, $this->roundingMode);
        }

        return $bigDecimal;
    }

    /**
     * Mengubah object BigDecimal (atau angka biasa) menjadi string untuk disimpan ke Database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            // Pastikan value menjadi BigDecimal dulu untuk diproses scaling-nya
            $bigDecimal = ($value instanceof BigDecimal)
                ? $value
                : BigDecimal::of(strval($value));

            // Terapkan scaling SEBELUM masuk database.
            // Ini penting agar data di DB sesuai dengan aturan bisnis (misal: max 2 desimal).
            if ($this->scale !== null) {
                $bigDecimal = $bigDecimal->toScale($this->scale, $this->roundingMode);
            }

            // Kembalikan sebagai string agar presisi terjaga di kolom DECIMAL database
            return (string) $bigDecimal;
        } catch (Exception) {
            throw new InvalidArgumentException("Value for attribute [$key] must be numeric or BigDecimal.");
        }
    }
}
