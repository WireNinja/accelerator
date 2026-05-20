<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model\Concerns;

use Spatie\Activitylog\Support\LogOptions;

trait BuildsConfiguredActivityOptions
{
    public function getActivitylogOptions(): LogOptions
    {
        $modelConfig = $this->activityModelConfig();

        return LogOptions::defaults()
            ->useLogName($this->activityLogName($modelConfig))
            ->logOnly($this->activityLoggedAttributes($modelConfig))
            ->logExcept($this->activityExcludedAttributes($modelConfig))
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, mixed>
     */
    private function activityModelConfig(): array
    {
        $config = config('audit.models.'.static::class, []);

        return is_array($config) ? $config : [];
    }

    /**
     * @param  array<string, mixed>  $modelConfig
     */
    private function activityLogName(array $modelConfig): string
    {
        return is_string($modelConfig['log_name'] ?? null)
            ? $modelConfig['log_name']
            : str($this->getTable())->singular()->snake()->toString();
    }

    /**
     * @param  array<string, mixed>  $modelConfig
     * @return array<int, string>
     */
    private function activityLoggedAttributes(array $modelConfig): array
    {
        $attributes = $modelConfig['attributes'] ?? [];

        return is_array($attributes) ? array_values($attributes) : [];
    }

    /**
     * @param  array<string, mixed>  $modelConfig
     * @return array<int, string>
     */
    private function activityExcludedAttributes(array $modelConfig): array
    {
        $defaultExcept = config('audit.default_except', []);
        $modelExcept = $modelConfig['except'] ?? [];

        return [
            ...(is_array($defaultExcept) ? array_values($defaultExcept) : []),
            ...(is_array($modelExcept) ? array_values($modelExcept) : []),
        ];
    }
}
