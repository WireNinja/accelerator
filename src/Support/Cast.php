<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use InvalidArgumentException;
use Stringable;
use UnitEnum;

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

    public static function filledString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cast = self::mustString($value);

        return trim($cast) === '' ? null : $cast;
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

    /** @return array<string, mixed> */
    public static function stringKeyedArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /** @return array<mixed, mixed> */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return list<mixed> */
    public static function list(mixed $value): array
    {
        return array_values(self::array($value));
    }

    /** @return list<string> */
    public static function stringList(mixed $value): array
    {
        return array_values(array_filter(self::array($value), is_string(...)));
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** @return class-string<UnitEnum>|null */
    public static function unitEnumClass(mixed $value): ?string
    {
        if (! is_string($value) || ! enum_exists($value) || ! is_subclass_of($value, UnitEnum::class)) {
            return null;
        }

        return $value;
    }

    /** @return class-string<UnitEnum> */
    public static function mustUnitEnumClass(mixed $value): string
    {
        $enumClass = self::unitEnumClass($value);

        if ($enumClass === null) {
            throw new InvalidArgumentException('Value must be a unit enum class name.');
        }

        return $enumClass;
    }

    /** @return class-string */
    public static function mustClassString(mixed $value): string
    {
        if (! is_string($value) || ! class_exists($value)) {
            throw new InvalidArgumentException('Value must be an existing class name.');
        }

        return $value;
    }
}
