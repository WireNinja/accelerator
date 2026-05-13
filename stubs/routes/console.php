<?php

declare(strict_types=1);

use WireNinja\Accelerator\Support\BuiltinSystemSchedule;

BuiltinSystemSchedule::dbBackup();
BuiltinSystemSchedule::filesBackup();
// BuiltinSystemSchedule::flushLastSeen();
// BuiltinSystemSchedule::ticketNotifyOverdue();
BuiltinSystemSchedule::snapshotHorizon();
