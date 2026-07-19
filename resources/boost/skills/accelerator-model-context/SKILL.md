---
name: accelerator-model-context
description: Inspect Accelerator Laravel model, relationship, cast, schema, and diagnostic context before changing domain models.
---

# Accelerator Model Context

## Commands

Use Laravel's native inspector for the normal model overview:

```bash
php artisan model:show User --json
```

Use Accelerator only for its additional schema-key and convention diagnostics:

```bash
php artisan agent:model-context --list --compact
php artisan agent:model-context User --compact
php artisan agent:model-context User --expand --compact
php artisan agent:model-context --all --compact
```

Default output without a model is the lightweight application-model registry. `--all` is explicit because a complete application scan can be large. `--expand` adds table indexes, foreign keys, relation key descriptors, events, observers, hidden attributes, and other model internals.

## Rules

- Inspect the model, migration, casts, relationships, factory, and policy before changing behavior.
- Treat command output as navigation and diagnostics, not proof of runtime correctness.
- Use explicit relationship return types and native Eloquent casts as source code truth.
- Use Larastan for static model typing. Do not generate giant model PHPDoc blocks or make runtime behavior depend on PHPDoc.
- For Eloquent static calls, prefer `Model::query()->...` when strict analysis requires it.
- If an enum-cast attribute has metadata helpers, keep the enum as the single source of truth.
- Do not add columns, relationships, policies, or lifecycle actions from scanner hints alone.

## Resource Context

When a model is exposed through Filament, also inspect its resource:

```bash
php artisan agent:resource-context {resource} --compact
```

Confirm reported violations are in scope before changing unrelated resource code.
