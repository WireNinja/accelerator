<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;
use WireNinja\Accelerator\Services\AcceleratedUserService;

final class BuiltinSystemSchedule
{
    public static function registerAll(): void
    {
        self::dbBackup();
        self::filesBackup();
        self::fullBackup();
        self::flushLastSeen();
        self::ticketNotifyOverdue();
        self::snapshotHorizon();
    }

    public static function dbBackup(): Event
    {
        return Schedule::command('backup:run --only-db')->dailyAt('01:00');
    }

    public static function filesBackup(): Event
    {
        return Schedule::command('backup:run --only-files')->dailyAt('02:00');
    }

    public static function fullBackup(): Event
    {
        return Schedule::command('backup:run')->dailyAt('03:00');
    }

    public static function flushLastSeen(): Event
    {
        return AcceleratedUserService::schedule();
    }

    public static function ticketNotifyOverdue(): Event
    {
        return Schedule::command('ticket:notify-overdue')->hourly()->withoutOverlapping();
    }

    public static function snapshotHorizon(): Event
    {
        return Schedule::command('horizon:snapshot')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }
}
