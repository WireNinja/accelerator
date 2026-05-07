<?php

namespace WireNinja\Accelerator\Filament\Traits;

use BackedEnum;
use UnitEnum;
use WireNinja\Accelerator\Enums\Concerns\MustBeResourceEnum;

trait BetterResource
{
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
