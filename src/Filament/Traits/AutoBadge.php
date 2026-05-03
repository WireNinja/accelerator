<?php

namespace WireNinja\Accelerator\Filament\Traits;

use Illuminate\Support\Facades\Cache;

trait AutoBadge
{
    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(static::class.':count', [60, 120], function () {
            return (string) static::getModel()::count();
        });
    }
}
