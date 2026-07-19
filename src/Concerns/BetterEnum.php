<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Concerns;

use BackedEnum;

trait BetterEnum
{
    public function is(BackedEnum|string|int|null $candidate): bool
    {
        return $this === static::resolve($candidate);
    }

    public function isNot(BackedEnum|string|int|null $candidate): bool
    {
        return ! $this->is($candidate);
    }

    /**
     * @param  iterable<int, BackedEnum|string|int|null>  $candidates
     */
    public function isAny(iterable $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->is($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<int, BackedEnum|string|int|null>  $candidates
     */
    public function isNone(iterable $candidates): bool
    {
        return ! $this->isAny($candidates);
    }

    public static function resolve(BackedEnum|string|int|null $candidate): ?static
    {
        if ($candidate instanceof static) {
            return $candidate;
        }

        if ($candidate instanceof BackedEnum) {
            $candidate = $candidate->value;
        }

        foreach (static::cases() as $case) {
            if ($case->name === $candidate) {
                return $case;
            }

            if ($case->value === $candidate) {
                return $case;
            }
        }

        return null;
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
