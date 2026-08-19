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
    /** @var int<0, max>|null */
    private readonly ?int $scale;

    private readonly RoundingMode $roundingMode;

    /**
     * Constructor accepts parameters from the cast definition in the Model.
     * Example: BigDecimalCast::class . ':2,DOWN'
     * Laravel passes parameters as strings.
     */
    public function __construct(
        string|int|null $scale = null,
        string|RoundingMode $roundingMode = RoundingMode::HalfUp,
    ) {
        $this->scale = self::resolveScale($scale);
        $this->roundingMode = self::resolveRoundingMode($roundingMode);
    }

    /**
     * Static helper for convenient usage in Model cast definitions.
     * Usage: BigDecimalCast::scale(2, RoundingMode::DOWN)
     */
    public static function scale(int $scale, RoundingMode $roundingMode = RoundingMode::HalfUp): string
    {
        if ($scale < 0) {
            throw new InvalidArgumentException(sprintf(
                'BigDecimalCast scale must be >= 0, received [%d].',
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

        // Must not silent fail. If DB data is corrupt, we MUST know —
        // returning zero silently = financial data corruption without a trace.
        try {
            $bigDecimal = BigDecimal::of(self::stringify($value));
        } catch (MathException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Column [%s] value on model [%s] cannot be cast to BigDecimal: %s',
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
     * Convert BigDecimal (or plain number) to string for database storage.
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

            // Apply scaling BEFORE persisting to database so stored data matches
            // business rules (e.g. max 2 decimal places).
            if ($this->scale !== null) {
                $bigDecimal = $bigDecimal->toScale($this->scale, $this->roundingMode);
            }

            // Return as string to preserve precision in DECIMAL database columns.
            return (string) $bigDecimal;
        } catch (MathException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Value for attribute [%s] must be numeric or BigDecimal: %s',
                    $key,
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /** @return int<0, max>|null */
    private static function resolveScale(string|int|null $scale): ?int
    {
        if ($scale === null || $scale === '') {
            return null;
        }

        $resolved = (int) $scale;

        if ($resolved < 0) {
            throw new InvalidArgumentException(sprintf(
                'BigDecimalCast scale must be >= 0, received [%s].',
                (string) $scale,
            ));
        }

        return $resolved;
    }

    /**
     * Convert string -> RoundingMode unit enum.
     *
     * Fails loud on typos in cast definitions. Previously used rescue() to HalfUp,
     * but that hides critical financial configuration bugs.
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
                'RoundingMode "%s" is not valid. Available options: %s.',
                $roundingMode,
                implode(', ', array_column($cases, 'name')),
            ));
        }

        return $lookup[$normalized];
    }

    /**
     * Convert mixed value -> string safe for BigDecimal::of().
     *
     * Boundary cast from external sources (DB driver string|int|float|object) to string.
     * This is one of the places where manual casting is valid because it IS a boundary,
     * NOT domain flow. For domain flow use Cast.
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
            'BigDecimalCast cannot stringify value of type [%s].',
            get_debug_type($value),
        ));
    }
}
