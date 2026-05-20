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
- Do not log sensitive attributes. Keep secrets in `config('audit.default_except')`.
- Relationship changes are not automatically captured by normal model events. Use the resource page trait or explicit `RelationshipActivityLogger` around custom actions that sync relationships.
- Filament discovery uses only `DiscoverAsResource` on the resource class. Do not add companion discovery attributes to pages, relation managers, forms, tables, or widgets.

## Existing Audit Layer

Core files:

- `config/audit.php`: per-model audit config.
- `app/Models/Concerns/HasConfiguredActivity.php`: for `User`-like models that are both subject and causer.
- `app/Models/Concerns/LogsConfiguredActivity.php`: for normal Eloquent models and custom pivot models.
- `app/Support/ActivityLog/RelationshipActivityLogger.php`: relationship snapshot/diff logging.
- `app/Filament/Concerns/LogsResourceRelationshipActivity.php`: Filament create/edit lifecycle hook integration.
- `app/Filament/RelationManagers/ActivitiesRelationManager.php`: reusable read-only activity relation manager.

## Add Activity Logging To A Model

1. Add the model to `config/audit.php`:
   - `log_name`: short domain log name.
   - `attributes`: explicit allow-list of columns and direct related attributes using dot notation.
   - `relationships`: optional snapshot declarations like `'roles' => 'roles.name'`.
2. Add the right trait to the model:
   - `HasConfiguredActivity` for `App\Models\User`.
   - `LogsConfiguredActivity` for ordinary models.
   - `LogsConfiguredActivity` for custom pivot models only when the pivot table has an `id` primary key and the pivot model has `$incrementing = true`.
3. Never use `logAll()` for business models unless the user explicitly accepts the sensitive/noisy blast radius.

## Add Activity Logging To A Filament Resource

1. Ensure the model has an `activities()` relation from `HasConfiguredActivity` or `LogsConfiguredActivity`.
2. Add `ActivitiesRelationManager::class` to the resource `getRelations()` array.
3. Add `LogsResourceRelationshipActivity` to the Create and Edit resource pages if the resource form saves relationships declared in `config/audit.php`.
4. For custom table/header actions that manually call `sync()`, `attach()`, `detach()`, or similar relationship writes, wrap the action:

```php
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
