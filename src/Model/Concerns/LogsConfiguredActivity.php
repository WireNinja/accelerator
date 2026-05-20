<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

trait LogsConfiguredActivity
{
    use BuildsConfiguredActivityOptions;
    use LogsActivity {
        BuildsConfiguredActivityOptions::getActivitylogOptions insteadof LogsActivity;
    }

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }
}
