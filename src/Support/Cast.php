<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use InvalidArgumentException;
use Stringable;

final class Cast
{
    public static function asString(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return $default;
    }

    public static function strictString(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Value cannot be cast to string.');
    }

    public static function asInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return is_numeric($value) ? (int) $value : $default;
    }
}
