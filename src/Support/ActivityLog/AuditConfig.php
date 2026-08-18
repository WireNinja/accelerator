<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{log_name: string|null, attributes: array<int, string>, except: array<int, string>, relationships: array<string, string>, attribute_labels: array<string, string>} forModel(string|Model $model)
 * @method static string logName(Model $model)
 * @method static array<int, string> attributes(string|Model $model)
 * @method static array<int, string> except(string|Model $model)
 * @method static array<string, string> relationships(string|Model $model)
 * @method static array<string, string> attributeLabels(string|Model $model)
 * @method static void flush()
 *
 * @see AuditConfiguration
 */
final class AuditConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditConfiguration::class;
    }
}
