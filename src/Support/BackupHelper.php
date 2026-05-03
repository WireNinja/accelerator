<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;

class BackupHelper
{
    /**
     * Schedule a database-only backup run.
     */
    public static function db(): Event
    {
        return Schedule::command('backup:run --only-db')->dailyAt('01:00');
    }

    /**
     * Schedule a files-only backup run.
     */
    public static function files(): Event
    {
        return Schedule::command('backup:run --only-files')->dailyAt('02:00');
    }

    /**
     * Schedule a full backup run.
     */
    public static function all(): Event
    {
        return Schedule::command('backup:run')->dailyAt('03:00');
    }
}
