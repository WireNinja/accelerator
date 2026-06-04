<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;

final class AuditConfig
{
    /**
     * @var array<class-string, array{log_name: string|null, attributes: array<int, string>, except: array<int, string>, relationships: array<string, string>, attribute_labels: array<string, string>}>
     */
    private static array $modelConfigs = [];

    /**
     * @var array<int, string>|null
     */
    private static ?array $defaultExcept = null;

    /**
     * @return array{log_name: string|null, attributes: array<int, string>, except: array<int, string>, relationships: array<string, string>, attribute_labels: array<string, string>}
     */
    public static function forModel(string | Model $model): array
    {
        $modelClass = is_string($model) ? $model : $model::class;

        return self::$modelConfigs[$modelClass] ??= self::normalizeModelConfig($modelClass);
    }

    public static function logName(Model $model): string
    {
        $logName = self::forModel($model)['log_name'];

        return is_string($logName)
            ? $logName
            : str($model->getTable())->singular()->snake()->toString();
    }

    /**
     * @return array<int, string>
     */
    public static function attributes(string | Model $model): array
    {
        return self::forModel($model)['attributes'];
    }

    /**
     * @return array<int, string>
     */
    public static function except(string | Model $model): array
    {
        return self::forModel($model)['except'];
    }

    /**
     * @return array<string, string>
     */
    public static function relationships(string | Model $model): array
    {
        return self::forModel($model)['relationships'];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeLabels(string | Model $model): array
    {
        return self::forModel($model)['attribute_labels'];
    }

    public static function flush(): void
    {
        self::$modelConfigs = [];
        self::$defaultExcept = null;
    }

    /**
     * @return array{log_name: string|null, attributes: array<int, string>, except: array<int, string>, relationships: array<string, string>, attribute_labels: array<string, string>}
     */
    private static function normalizeModelConfig(string $modelClass): array
    {
        $config = config('audit.models.'.$modelClass, []);
        $config = is_array($config) ? $config : [];

        $modelExcept = $config['except'] ?? [];

        return [
            'log_name' => is_string($config['log_name'] ?? null) ? $config['log_name'] : null,
            'attributes' => self::attributeList($config['attributes'] ?? []),
            'except' => [
                ...self::defaultExcept(),
                ...self::stringList($modelExcept),
            ],
            'relationships' => self::stringMap($config['relationships'] ?? []),
            'attribute_labels' => [
                ...self::attributeLabelMap($config['attributes'] ?? []),
                ...self::stringMap($config['attribute_labels'] ?? []),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function defaultExcept(): array
    {
        return self::$defaultExcept ??= self::stringList(config('audit.default_except', []));
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<int, string>
     */
    private static function attributeList(mixed $value): array
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

    /**
     * @return array<string, string>
     */
    private static function attributeLabelMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || ! is_string($item)) {
                continue;
            }

            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || ! is_string($item)) {
                continue;
            }

            $map[$key] = $item;
        }

        return $map;
    }
}
