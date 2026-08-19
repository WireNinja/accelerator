<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use WireNinja\Accelerator\Support\Cast;

final class AuditConfiguration
{
    /**
     * @var array<class-string, array{log_name: string|null, attributes: array<int, string>, except: array<int, string>, relationships: array<string, string>, attribute_labels: array<string, string>}>
     */
    private array $modelConfigs = [];

    /** @var array<int, string>|null */
    private ?array $defaultExcept = null;

    /**
     * @return array{log_name: string|null, attributes: array<int, string>, except: array<int, string>, relationships: array<string, string>, attribute_labels: array<string, string>}
     */
    public function forModel(string|Model $model): array
    {
        $modelClass = Cast::mustClassString(is_string($model) ? $model : $model::class);

        return $this->modelConfigs[$modelClass] ??= $this->normalizeModelConfig($modelClass);
    }

    public function logName(Model $model): string
    {
        $logName = $this->forModel($model)['log_name'];

        return is_string($logName)
            ? $logName
            : str($model->getTable())->singular()->snake()->toString();
    }

    /** @return array<int, string> */
    public function attributes(string|Model $model): array
    {
        return $this->forModel($model)['attributes'];
    }

    /** @return array<int, string> */
    public function except(string|Model $model): array
    {
        return $this->forModel($model)['except'];
    }

    /** @return array<string, string> */
    public function relationships(string|Model $model): array
    {
        return $this->forModel($model)['relationships'];
    }

    /** @return array<string, string> */
    public function attributeLabels(string|Model $model): array
    {
        return $this->forModel($model)['attribute_labels'];
    }

    public function flush(): void
    {
        $this->modelConfigs = [];
        $this->defaultExcept = null;
    }

    /**
     * @param  class-string  $modelClass
     * @return array{log_name: string|null, attributes: array<int, string>, except: array<int, string>, relationships: array<string, string>, attribute_labels: array<string, string>}
     */
    private function normalizeModelConfig(string $modelClass): array
    {
        $config = config('accelerator.audit.models.'.$modelClass, []);
        $config = is_array($config) ? $config : [];
        $modelExcept = $config['except'] ?? [];

        return [
            'log_name' => is_string($config['log_name'] ?? null) ? $config['log_name'] : null,
            'attributes' => $this->attributeList($config['attributes'] ?? []),
            'except' => [
                ...$this->defaultExcept(),
                ...Cast::stringList($modelExcept),
            ],
            'relationships' => $this->stringMap($config['relationships'] ?? []),
            'attribute_labels' => [
                ...$this->attributeLabelMap($config['attributes'] ?? []),
                ...$this->stringMap($config['attribute_labels'] ?? []),
            ],
        ];
    }

    /** @return array<int, string> */
    private function defaultExcept(): array
    {
        return $this->defaultExcept ??= Cast::stringList(config('accelerator.audit.default_except', []));
    }

    /** @return array<int, string> */
    private function attributeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $attributes = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $attributes[] = $key;

                continue;
            }

            if (is_string($item)) {
                $attributes[] = $item;
            }
        }

        return array_values(array_unique($attributes));
    }

    /** @return array<string, string> */
    private function attributeLabelMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /** @return array<string, string> */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }
}
