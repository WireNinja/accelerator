<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Traits;

trait WidgetMustBeLazy
{
    public static function isLazy(): bool
    {
        return true;
    }
}
