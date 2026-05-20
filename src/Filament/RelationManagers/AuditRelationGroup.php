<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\RelationManagers;

use Closure;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;

final class AuditRelationGroup
{
    /**
     * @param  array<class-string<RelationManager>|RelationManagerConfiguration>  $managers
     */
    public static function make(array $managers, string | Closure $label = 'Audit'): RelationGroup
    {
        return RelationGroup::make($label, $managers)
            ->deferBadge();
    }
}
