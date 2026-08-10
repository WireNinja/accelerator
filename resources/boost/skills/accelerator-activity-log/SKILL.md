---
name: accelerator-activity-log
description: Add or review Spatie laravel-activitylog v5 auditing in Accelerator models and Filament resources, including config allowlists, model traits, relationship snapshots, and the shared read-only activity relation manager.
---

# Accelerator Activity Log

## Model workflow

1. Add an explicit model entry to `config/audit.php`.
2. Allowlist business attributes; keep secrets in `default_except`.
3. Use `HasConfiguredActivity` for the User/causer model and `LogsConfiguredActivity` for ordinary models.
4. Use a custom pivot model only when it has an integer `id` and `$incrementing = true`.

Do not copy `getActivitylogOptions()` into models and do not use `logAll()` without explicit approval of the noise/security impact.

## Relationship changes

Normal model events do not capture `sync`/`attach`/`detach` differences. For Filament create/edit pages that save configured relationships, use `LogsResourceRelationshipActivity`. Inject the logger into custom domain Actions or Services:

```php
final class UpdateOrderItems
{
    public function __construct(
        private RelationshipActivityLogger $activityLogger,
    ) {}

    public function handle(Order $order, array $items): void
    {
        $before = $this->activityLogger->snapshot($order);
        $order->items()->sync($items);
        $this->activityLogger->logIfChanged($order, $before);
    }
}
```

Keep `ActivitiesRelationManager` read-only and group it with native `RelationGroup` when exposing audit history.

`AuditConfig` caches normalized metadata per Octane worker. Flush it only when runtime config is deliberately mutated in an interactive diagnostic session.

## Verification

Inspect the model/resource source, run scoped Pint and static analysis, then exercise the affected authenticated resource page. Use compact model/resource context only if the command exists and its output is relevant.
