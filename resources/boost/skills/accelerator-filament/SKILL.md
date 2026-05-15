---
name: accelerator-filament
description: Build Filament resources the Accelerator way with BetterResource, ResourceEnum, Shield regeneration, discovery annotations, and strict Filament v5 conventions.
---

# Accelerator Filament

## When To Use

Use this skill when creating, reviewing, or refactoring Filament panels, resources, forms, tables, actions, widgets, policies, or Shield permissions in an Accelerator project.

## Core Rules

- Follow the application's `misc/llm/FILAMENT.md` when present. It is the local source of truth.
- Do not refactor working Filament APIs unless the local standard explicitly marks them deprecated.
- Use Filament v5 APIs:
  - `Action::schema()`, not `Action::form()`.
  - `ImageColumn::imageSize()`, not `ImageColumn::size()`.
  - `Filament\Actions\*` unified action namespace unless a table-specific API is truly required.
  - `Filament\Schemas\Components\Utilities\Get` and `Set`.
- Use `mustUser()` in authenticated admin surfaces instead of weakening the invariant with nullable `user()`.
- Keep authorization in policies with `authorize()` or string abilities. Do not use `visible()`/`hidden()` for permissions.

## Resource Checklist

For new or changed resources:

- register the resource in `ResourceEnum`
- use `BetterResource` on the resource class
- split form/table logic into separate classes when the project pattern does so
- add discovery attributes such as `DiscoverAsResource`, `DiscoverAsForm`, `DiscoverAsTable`, and relevant page/relation/widget attributes
- use `->columns(12)` at the root schema instead of wrapping the whole form in `Grid::make(12)`
- use Lucide icon names with the `lucide-` prefix
- remove all bulk action APIs unless the user explicitly approves an exception
- do not redefine labels for default Edit/Delete actions just to localize them
- use enum metadata directly for labels, colors, icons, and options

## Shield

- Do not create Filament resource policies with `php artisan make:policy`.
- Register resources first, then run:

```bash
php artisan shield:safe-regenerate
```

Review generated policies and customize only what the domain requires.

## Discovery

Use the context command before broad edits:

```bash
php artisan agent:resource-context user --compact
```

Use `--expand` only when the compact payload hides details needed for the task.

## Final Response

When you touch or audit Filament resources, end with a concrete checklist covering what was done and what was intentionally not done.
