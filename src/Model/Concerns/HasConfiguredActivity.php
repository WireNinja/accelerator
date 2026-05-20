<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model\Concerns;

use Spatie\Activitylog\Models\Concerns\HasActivity;

trait HasConfiguredActivity
{
    use BuildsConfiguredActivityOptions;
    use HasActivity {
        BuildsConfiguredActivityOptions::getActivitylogOptions insteadof HasActivity;
    }
}
