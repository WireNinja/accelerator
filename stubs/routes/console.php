<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run --only-db')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('backup:run --only-files')->dailyAt('02:00')->withoutOverlapping();

if (config('accelerator.features.ticketing')) {
    Schedule::command('ticket:notify-overdue')->hourly()->withoutOverlapping();
}

if (config('accelerator.features.horizon')) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
}

if (config('accelerator.features.telemetry')) {
    Schedule::command('telemetry:prune')->dailyAt('04:00')->withoutOverlapping();
}
