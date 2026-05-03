<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Traits;

trait DisableWidgetPolling
{
    protected function getPollingInterval(): ?string
    {
        return null;
    }
}
