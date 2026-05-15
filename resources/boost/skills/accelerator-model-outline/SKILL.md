---
name: accelerator-model-outline
description: Use Accelerator model outline, model audit, model docs, and agent context commands to inspect Laravel domain models before changing them.
---

# Accelerator Model Outline

## When To Use

Use this skill when working with Eloquent models, relationships, casts, model documentation, schema discovery, resource context, or AI-facing codebase summaries in an Accelerator project.

## Commands

Prefer Accelerator inspection commands before manual drilling:

```bash
php artisan accelerator:generate-model-outline
php artisan accelerator:model-audit
php artisan accelerator:model-doc
php artisan agent:model-context
php artisan agent:resource-context {resource} --compact
```

Use command `--help` to confirm available options before assuming flags.

## Rules

- Inspect the existing model, migration, casts, relationships, factories, and policies before changing behavior.
- Treat generated outlines as navigation aids, not as proof that runtime behavior is correct.
- For Eloquent static calls, prefer `Model::query()->...` when the project enforces strict analysis.
- If an attribute is enum-cast and the enum has metadata helpers, use the enum as the single source of truth.
- Do not add database columns, relationships, policies, or lifecycle actions from outline hints alone. User approval or explicit requirements are required.

## Resource Context

When editing Filament resources, use resource context to understand:

- form schema structure
- table columns and actions
- policy abilities referenced by actions
- relation managers, pages, and widgets
- discovery gaps that need annotations

If the context command reports a violation, confirm whether it is in scope before changing unrelated resource code.
