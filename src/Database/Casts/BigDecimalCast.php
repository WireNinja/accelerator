<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Database\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<BigDecimal|null, string|int|float|BigDecimal|null>
 */
class BigDecimalCast implements CastsAttributes
{
    private readonly ?int $scale;

    private readonly RoundingMode $roundingMode;

    /**
     * Constructor menerima parameter dari definisi cast di Model.
     * Contoh: BigDecimalCast::class . ':2,DOWN'
     * Laravel akan mengirim parameter sebagai string.
     */
    public function __construct(
        string|int|null $scale = null,
        string|RoundingMode $roundingMode = RoundingMode::HalfUp,
    ) {
        $this->scale = self::resolveScale($scale);
        $this->roundingMode = self::resolveRoundingMode($roundingMode);
    }

    /**
     * Helper static untuk mempermudah penulisan di Model.
     * Penggunaan: BigDecimalCast::scale(2, RoundingMode::DOWN)
     */
    public static function scale(int $scale, RoundingMode $roundingMode = RoundingMode::HalfUp): string
    {
        if ($scale < 0) {
            throw new InvalidArgumentException(sprintf(
                'BigDecimalCast scale harus >= 0, diterima [%d].',
                $scale,
            ));
        }

        return self::class.sprintf(':%d,%s', $scale, $roundingMode->name);
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        if ($value === null) {
            return null;
        }

        // Tidak boleh silent fail. Jika data di DB korup, kita HARUS tahu —
        // mengembalikan zero diam-diam = data keuangan corrupt tanpa jejak.
        try {
            $bigDecimal = BigDecimal::of(self::stringify($value));
        } catch (MathException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Nilai kolom [%s] pada model [%s] tidak bisa di-cast ke BigDecimal: %s',
                    $key,
                    $model::class,
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }

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
            $bigDecimal = $value instanceof BigDecimal
                ? $value
                : BigDecimal::of(self::stringify($value));

            // Terapkan scaling SEBELUM masuk database supaya data di DB sesuai dengan
            // aturan bisnis (misal: max 2 desimal).
            if ($this->scale !== null) {
                $bigDecimal = $bigDecimal->toScale($this->scale, $this->roundingMode);
            }

            // Kembalikan sebagai string agar presisi terjaga di kolom DECIMAL database.
            return (string) $bigDecimal;
        } catch (MathException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Nilai untuk attribute [%s] harus numeric atau BigDecimal: %s',
                    $key,
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    private static function resolveScale(string|int|null $scale): ?int
    {
        if ($scale === null || $scale === '') {
            return null;
        }

        $resolved = (int) $scale;

        if ($resolved < 0) {
            throw new InvalidArgumentException(sprintf(
                'BigDecimalCast scale harus >= 0, diterima [%s].',
                (string) $scale,
            ));
        }

        return $resolved;
    }

    /**
     * Konversi string -> RoundingMode unit enum.
     *
     * Fail loud kalau definisi cast salah ketik. Sebelumnya pakai rescue() ke HalfUp,
     * tapi itu menyembunyikan bug konfigurasi keuangan yang sangat fatal.
     */
    private static function resolveRoundingMode(string|RoundingMode $roundingMode): RoundingMode
    {
        if ($roundingMode instanceof RoundingMode) {
            return $roundingMode;
        }

        $cases = RoundingMode::cases();
        $lookup = [];

        foreach ($cases as $case) {
            $lookup[strtoupper($case->name)] = $case;
        }

        $normalized = strtoupper($roundingMode);

        if (! array_key_exists($normalized, $lookup)) {
            throw new InvalidArgumentException(sprintf(
                'RoundingMode "%s" tidak valid. Pilihan yang tersedia: %s.',
                $roundingMode,
                implode(', ', array_column($cases, 'name')),
            ));
        }

        return $lookup[$normalized];
    }

    /**
     * Konversi nilai mixed -> string yang aman di-feed ke BigDecimal::of().
     *
     * Boundary cast eksternal (DB driver string|int|float|object) ke string. Ini salah
     * satu tempat di mana cast manual masih sah karena memang boundary, BUKAN flow
     * domain. Untuk flow domain pakai TypeCaster.
     */
    private static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf(
            'BigDecimalCast tidak bisa stringify nilai bertipe [%s].',
            get_debug_type($value),
        ));
    }
}
