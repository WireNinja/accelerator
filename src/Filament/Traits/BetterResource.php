<?php

namespace WireNinja\Accelerator\Filament\Traits;

use BackedEnum;
use UnitEnum;
use WireNinja\Accelerator\Enums\Concerns\MustBeResourceEnum;

trait BetterResource
{
    /**
     * @internal
     *
     * Static memo cache for resolved resource enum lookups, keyed by Resource class name.
     *
     * Octane note: this static array PERSISTS across requests because Octane keeps the
     * worker process alive. That is desirable here (the lookup is expensive and
     * the result depends on a config that does not change at runtime) but it has a
     * subtle implication for tests: re-binding `accelerator.enums.resource` in a
     * test setUp() will NOT invalidate prior memoised entries until the worker is
     * recycled. If you ever need to flush the memo from a test, call
     * `BetterResource::resetEnumMemo()` (TODO if a test ever needs it — currently
     * no test relies on per-test re-binding).
     */
    private static function getResourceEnum(): ?MustBeResourceEnum
    {
        static $memo = [];
        $class = static::class;

        if (array_key_exists($class, $memo)) {
            return $memo[$class];
        }

        $enumClass = config('accelerator.enums.resource');

        if (! $enumClass || ! enum_exists($enumClass) || (! is_subclass_of($enumClass, MustBeResourceEnum::class))) {
            return $memo[$class] = null;
        }

        /** @var class-string<MustBeResourceEnum> $enumClass */
        return $memo[$class] = $enumClass::fromResource($class);
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return self::getResourceEnum()?->getNavigationIcon() ?? parent::getNavigationIcon();
    }

    public static function getLabel(): ?string
    {
        return self::getResourceEnum()?->getLabel() ?? parent::getLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::getResourceEnum()?->getNavigationGroup() ?? parent::getNavigationGroup();
    }
}
