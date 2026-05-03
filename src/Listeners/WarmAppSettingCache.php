<?php

namespace WireNinja\Accelerator\Listeners;

use Laravel\Octane\Events\WorkerStarting;
use WireNinja\Accelerator\Settings\SystemSettings;

class WarmAppSettingCache
{
    public function handle(WorkerStarting $event): void
    {
        resolve(SystemSettings::class);
    }
}
