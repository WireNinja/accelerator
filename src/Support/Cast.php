<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

final class Cast
{
    public static function asString(mixed $value, string $default = ''): string
    {
        return TypeCaster::safeString($value, $default);
    }

    public static function strictString(mixed $value): string
    {
        return TypeCaster::strictString($value);
    }

    public static function asInt(mixed $value, int $default = 0): int
    {
        return TypeCaster::safeInt($value, $default);
    }

    public static function asBool(mixed $value, bool $default = false): bool
    {
        return TypeCaster::safeBool($value, $default);
    }
}
