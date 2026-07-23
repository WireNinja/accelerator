---
name: accelerator-model-context
description: Inspect Accelerator Laravel models, schema, casts, relationships, policies, factories, observers, and related Filament resources before model changes. Use for model design, Eloquent typing, relation keys, or compact AI context.
---

# Accelerator Model Context

## Inspection order

1. Read the model and relevant migration source.
2. Use Laravel's native inspector and database schema tools.
3. Inspect policy, factory, observers/events, and consuming resource only when relevant.
4. Use compact Accelerator context as a file map/diagnostic, never as runtime proof.

```bash
php artisan model:show User --json
php artisan accelerator:context model User
php artisan accelerator:context model User --expand
```

Verify command availability on the current v2 branch. Default Accelerator output should contain only identity, file/table/key, significant casts, relations, related files, and issues; deep indexes/events/source detail requires `--expand`.

## Rules

- Use explicit relationship return types and native Eloquent casts.
- Use Larastan for typing; do not generate giant model PHPDoc or runtime magic.
- Keep enum metadata in the enum and BigDecimal behavior in the shared cast/synth/input stack.
- Prefer `Model::query()` for static analysis clarity.
- Do not add schema, relations, policies, or lifecycle actions from scanner hints alone.
- If a Filament resource is affected, use `accelerator-filament` too.
