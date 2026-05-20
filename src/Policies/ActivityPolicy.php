<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    public function viewAny(Authenticatable $authUser): bool
    {
        return true;
    }

    public function view(Authenticatable $authUser, Activity $activity): bool
    {
        return true;
    }
}
