---
name: accelerator-filament
description: Build or review Accelerator Filament v5 panels, resources, forms, tables, actions, policies, Shield permissions, BigDecimal inputs, and custom components. Use for all Filament UI or authorization work, including resource context and verification.
---

# Accelerator Filament

## Workflow

1. Inspect the registered Filament panel/resource, model, policy, form, and table source.
2. Search version-specific Filament documentation before changing an unfamiliar API.
3. Prefer native Filament/Laravel behavior; add package abstractions only for repeated real invariants.
4. Make authorization policy-driven; UI visibility is not security.
5. Run the focused resource verifier if present, then static analysis and the affected authenticated page.

For an end-to-end model-backed business feature, activate `accelerator-feature-development`; this skill owns Filament-specific implementation details, not the whole domain workflow.

Context output is navigation, not truth. Do not add UI code merely to satisfy a scanner.

## Resource truth

Filament's registered panel/resource is authoritative. Use `accelerator:make-resource` for deterministic scaffolding. Navigation groups come from app-owned `App\Enums\System\NavigationGroup`; keep each resource icon, label, policy, and panel placement in the resource itself. Do not recreate `BetterResource` or a resource registry enum.

Run migrations before using `accelerator:make-resource --generate`. Schema-driven generation requires the current database table and may not be combined with `--migration`.

Custom Shield abilities belong in a permission-specific declaration, not navigation metadata. Super Admin bypass/access must remain valid after Shield regeneration.

## Authorization

- Use policies for resources and string abilities for actions.
- Do not use `visible()`/`hidden()` as permission checks.
- Avoid redundant `authorize()` on native CRUD actions already covered by policy.
- Put mutually exclusive state rules in policy methods.
- Block unsafe self-mutation and protect Super Admin targets explicitly.
- Run `shield:safe-regenerate` only through the Accelerator-safe workflow.

Bulk actions are disabled by default because auditability and per-record authorization are preferred. Add one only for an explicit business need with policy checks, per-record safety, activity logging, bounded workload, and a clear partial-failure strategy.

## Global defaults

Do not repeat package-wide configuration locally unless intentionally overriding it:

- Select: searchable, preloaded, non-native.
- Date/time pickers: non-native Indonesian display formats.
- File uploads: image editor, five parallel uploads, 100 MB logical maximum.
- Tables: cursor pagination, `id desc` default, IDR/id-ID formatting, deferred loading/filter/column manager, striped rows, generic empty state.

Defaults are overrideable. A resource may use another key/sort/pagination explicitly.

Use `->preload(false)` on high-cardinality relationship Selects. This is the intended local OOP override, not a reason to weaken the useful global default.

## Forms and tables

- Use professional Bahasa Indonesia for user-facing copy.
- Use Lucide icons and native enum label/color/icon contracts.
- Keep operational tables flat and make secondary columns toggleable.
- Use native relationship fields/repeaters before custom save hooks.
- Add custom actions only for real domain transitions or side effects.
- Use `BusinessException` for expected business-rule failures.
- Use `Action::schema()`, current Filament namespaces, and non-deprecated APIs.

For BigDecimal-cast attributes use `SeparatedNumberInput`, not `TextInput::numeric()`. Keep the package's separator convention unless the component implementation is redesigned as a whole.

Use the package map primitive instead of copying a project-local field:

```php
use WireNinja\Accelerator\Filament\Forms\Components\LocationPicker;

LocationPicker::make('latitude')
    ->longitudeField('longitude');
```

## Context and verification

Target compact inspection:

```bash
php artisan accelerator:context resource {resource}
php artisan accelerator:context resource {resource} --expand
php artisan accelerator:verify-resource {resource}
```

Default context does not execute field closures or instantiate schemas with a null record. The verifier is independent and enforces only registration, model, and policy invariants.

## Taste gate

Preserve the custom sidebar/topbar and VerticalWizard unless the owner explicitly changes product taste. Login uses Filament's native layout with the minimal Accelerator login class and OAuth render hook; do not recreate alternative login layouts.

The `TOPBAR_END` dual-stage environment badge is a safety invariant, not decoration. Keep it hidden for single-stage deployments and visible locally, on staging, and on production when dual topology is enabled. Default labels are `LOCAL DATA`, `TEST DATA`, and `LIVE DATA`. Labels and Filament badge colors come from `accelerator.ui.environment_indicator`.
