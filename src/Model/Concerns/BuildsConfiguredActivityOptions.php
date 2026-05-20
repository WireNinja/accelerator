<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model\Concerns;

use Spatie\Activitylog\Support\LogOptions;
use WireNinja\Accelerator\Support\ActivityLog\AuditConfig;

trait BuildsConfiguredActivityOptions
{
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(AuditConfig::logName($this))
            ->logOnly(AuditConfig::attributes($this))
            ->logExcept(AuditConfig::except($this))
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
