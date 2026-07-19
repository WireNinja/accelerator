<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use WireNinja\Accelerator\Support\UserModel;

class RelationshipActivityLogger
{
    /**
     * @return array<string, array<int, string>>
     */
    public function snapshot(Model $model): array
    {
        return collect(AuditConfig::relationships($model))
            ->mapWithKeys(fn (string $path, string $key): array => [$key => $this->snapshotRelationship($model, $path)])
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function emptySnapshot(Model $model): array
    {
        return collect(AuditConfig::relationships($model))
            ->mapWithKeys(fn (string $path, string $key): array => [$key => []])
            ->all();
    }

    /**
     * @param  array<string, array<int, string>>  $old
     */
    public function logIfChanged(Model $model, array $old, ?string $description = null): void
    {
        $new = $this->snapshot($model);

        if ($old === $new) {
            return;
        }

        activity(AuditConfig::logName($model))
            ->performedOn($model)
            ->causedBy(UserModel::current())
            ->event('relationships_updated')
            ->withChanges([
                'attributes' => $new,
                'old' => $old,
            ])
            ->log($description ?? 'Relasi data diperbarui');
    }

    /**
     * @return array<int, string>
     */
    private function snapshotRelationship(Model $model, string $path): array
    {
        [$relationshipName, $attribute] = explode('.', $path, 2) + [1 => 'id'];

        if (! method_exists($model, $relationshipName)) {
            return [];
        }

        /** @var Collection<int, Model> $records */
        $records = $model->{$relationshipName}()->get();

        return $records
            ->mapWithKeys(fn (Model $record): array => [
                (int) $record->getKey() => (string) data_get($record, $attribute, $record->getKey()),
            ])
            ->sortKeys()
            ->all();
    }
}
