<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use InvalidArgumentException;
use Stringable;

final class Cast
{
    /**
     * @return ($default is null ? string|null : string)
     */
    public static function string(mixed $value, ?string $default = ''): ?string
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return $default;
    }

    public static function mustString(mixed $value): string
    {
        $cast = self::string($value, null);

        if ($cast === null) {
            throw new InvalidArgumentException(sprintf(
                'Value of type [%s] cannot be cast to string.',
                get_debug_type($value),
            ));
        }

        return $cast;
    }

    /**
     * @return ($default is null ? int|null : int)
     */
    public static function int(mixed $value, ?int $default = 0): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function mustInt(mixed $value): int
    {
        $cast = self::int($value, null);

        if ($cast === null) {
            throw new InvalidArgumentException(sprintf(
                'Value of type [%s] cannot be cast to int.',
                get_debug_type($value),
            ));
        }

        return $cast;
    }
}
