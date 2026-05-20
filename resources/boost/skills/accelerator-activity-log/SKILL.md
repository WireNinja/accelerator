---
name: accelerator-activity-log
description: Set up reusable Spatie laravel-activitylog v5 audit logging for Accelerator Laravel models and Filament resources using config/audit.php, configured model traits, relationship snapshots, and the shared activity relation manager.
---

# Accelerator Activity Log

Use this skill when adding or reviewing activity logging for an Accelerator Laravel model or Filament resource.

## Ground Rules

- Spatie package version is v5. Use namespaces from v5:
  - `Spatie\Activitylog\Models\Concerns\HasActivity`
  - `Spatie\Activitylog\Models\Concerns\LogsActivity`
  - `Spatie\Activitylog\Support\LogOptions`
- Do not copy `getActivitylogOptions()` into every model. Use the configured traits and `config/audit.php`.
- `config/audit.php` is a userland published config. Accelerator ships the publish stub through `app-config`; do not merge `audit` config from the package service provider.
- Audit metadata is cached per Octane worker by `WireNinja\Accelerator\Support\ActivityLog\AuditConfig`. If a test or tinker session mutates `config('audit.*')` at runtime, call `AuditConfig::flush()` before asserting behavior.
- Do not log sensitive attributes. Keep secrets in `config('audit.default_except')`.
- Relationship changes are not automatically captured by normal model events. Use the resource page trait or explicit `RelationshipActivityLogger` around custom actions that sync relationships.
- Filament discovery uses only `DiscoverAsResource` on the resource class. Do not add companion discovery attributes to pages, relation managers, forms, tables, or widgets.

## Existing Audit Layer

Core files:

- `config/audit.php`: per-model audit config published by the `app-config` installer component.
- `WireNinja\Accelerator\Support\ActivityLog\AuditConfig`: static in-memory resolver for normalized audit config. It caches per-model metadata and default exclusions for Octane-friendly reads.
- `WireNinja\Accelerator\Model\Concerns\HasConfiguredActivity`: for `User`-like models that are both subject and causer.
- `WireNinja\Accelerator\Model\Concerns\LogsConfiguredActivity`: for normal Eloquent models and custom pivot models.
- `WireNinja\Accelerator\Support\ActivityLog\RelationshipActivityLogger`: relationship snapshot/diff logging.
- `WireNinja\Accelerator\Filament\Concerns\LogsResourceRelationshipActivity`: Filament create/edit lifecycle hook integration.
- `WireNinja\Accelerator\Filament\RelationManagers\ActivitiesRelationManager`: reusable read-only activity relation manager.
- `WireNinja\Accelerator\Filament\RelationManagers\AuditRelationGroup`: reusable audit relation group wrapper. Pass relation managers into `AuditRelationGroup::make([...])` so resources stay consistent and relation badges are deferred by default.
- `WireNinja\Accelerator\Policies\ActivityPolicy`: built-in policy for `Spatie\Activitylog\Models\Activity`. Accelerator registers it when no app policy exists, so strict Filament authorization works out of the box.

## Add Activity Logging To A Model

1. Add the model to `config/audit.php`:
   - `log_name`: short domain log name.
   - `attributes`: explicit allow-list of columns and direct related attributes using dot notation.
   - `relationships`: optional snapshot declarations like `'roles' => 'roles.name'`.
2. Add the right trait to the model:
   - `WireNinja\Accelerator\Model\Concerns\HasConfiguredActivity` for `App\Models\User`.
   - `WireNinja\Accelerator\Model\Concerns\LogsConfiguredActivity` for ordinary models.
   - `WireNinja\Accelerator\Model\Concerns\LogsConfiguredActivity` for custom pivot models only when the pivot table has an `id` primary key and the pivot model has `$incrementing = true`.
3. Never use `logAll()` for business models unless the user explicitly accepts the sensitive/noisy blast radius.

## Add Activity Logging To A Filament Resource

1. Ensure the model has an `activities()` relation from `HasConfiguredActivity` or `LogsConfiguredActivity`.
2. Add the audit relation group to the resource `getRelations()` array:

```php
use Filament\Resources\RelationManagers\RelationGroup;
use WireNinja\Accelerator\Filament\RelationManagers\ActivitiesRelationManager;
use WireNinja\Accelerator\Filament\RelationManagers\AuditRelationGroup;

/**
 * @return array<int, RelationGroup>
 */
public static function getRelations(): array
{
    return [
        AuditRelationGroup::make([
            ActivitiesRelationManager::class,
        ]),
    ];
}
```

3. Add `LogsResourceRelationshipActivity` to the Create and Edit resource pages if the resource form saves relationships declared in `config/audit.php`.
4. For custom table/header actions that manually call `sync()`, `attach()`, `detach()`, or similar relationship writes, wrap the action:

```php
use WireNinja\Accelerator\Support\ActivityLog\RelationshipActivityLogger;

$activityLogger = resolve(RelationshipActivityLogger::class);
$relationshipSnapshot = $activityLogger->snapshot($record);

// perform relationship mutation

$activityLogger->logIfChanged($record, $relationshipSnapshot);
```

5. Keep activity relation managers read-only. Do not add create/edit/delete actions for activity rows.

## Verification

Run the minimum verification:

```bash
vendor/bin/pint --dirty --format agent
composer phpstan
php artisan accelerator:verify-resource {resourceKey} --compact
```

If PHP model docs or typed column access changed, run the relevant `php artisan accelerator:model-doc {Model} --write` before verification.
