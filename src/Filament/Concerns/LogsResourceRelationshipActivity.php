<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use WireNinja\Accelerator\Support\ActivityLog\RelationshipActivityLogger;

trait LogsResourceRelationshipActivity
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $activityRelationshipSnapshot = [];

    protected function beforeSave(): void
    {
        $record = $this->getActivityRecord();

        if (! $record instanceof Model) {
            return;
        }

        $this->activityRelationshipSnapshot = $this->activityLogger()->snapshot($record);
    }

    protected function afterSave(): void
    {
        $record = $this->getActivityRecord();

        if (! $record instanceof Model) {
            return;
        }

        $this->activityLogger()->logIfChanged($record, $this->activityRelationshipSnapshot);
    }

    protected function afterCreate(): void
    {
        $record = $this->getActivityRecord();

        if (! $record instanceof Model) {
            return;
        }

        $this->activityLogger()->logIfChanged(
            $record,
            $this->activityLogger()->emptySnapshot($record),
            'Relasi data dibuat',
        );
    }

    private function activityLogger(): RelationshipActivityLogger
    {
        return resolve(RelationshipActivityLogger::class);
    }

    private function getActivityRecord(): ?Model
    {
        return $this->getRecord();
    }
}
