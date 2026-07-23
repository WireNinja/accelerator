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

Context output is navigation, not truth. Do not add UI code merely to satisfy a scanner.

## Resource truth

Filament's registered panel/resource is authoritative. Conventional paths are inferred; do not recreate duplicate resource enums, discovery attributes, or metadata traits.

Custom Shield abilities belong in a permission-specific declaration, not navigation metadata. Super Admin bypass/access must remain valid after Shield regeneration.

## Authorization

- Use policies for resources and string abilities for actions.
- Do not use `visible()`/`hidden()` as permission checks.
- Avoid redundant `authorize()` on native CRUD actions already covered by policy.
- Put mutually exclusive state rules in policy methods.
- Block unsafe self-mutation and protect Super Admin targets explicitly.
- Run `shield:safe-regenerate` only through the Accelerator-safe workflow.

Bulk actions remain forbidden for this distribution because auditability and per-record authorization are preferred.

## Global defaults

Do not repeat package-wide configuration locally unless intentionally overriding it:

- Select: searchable, preloaded, non-native.
- Date/time pickers: non-native Indonesian display formats.
- File uploads: image editor, five parallel uploads, 100 MB logical maximum.
- Tables: cursor pagination, `id desc` default, IDR/id-ID formatting, deferred loading/filter/column manager, striped rows, generic empty state.

Defaults are overrideable. A resource may use another key/sort/pagination explicitly.

## Forms and tables

- Use professional Bahasa Indonesia for user-facing copy.
- Use Lucide icons and native enum label/color/icon contracts.
- Keep operational tables flat and make secondary columns toggleable.
- Use native relationship fields/repeaters before custom save hooks.
- Add custom actions only for real domain transitions or side effects.
- Use `BusinessException` for expected business-rule failures.
- Use `Action::schema()`, current Filament namespaces, and non-deprecated APIs.

For BigDecimal-cast attributes use `SeparatedNumberInput`, not `TextInput::numeric()`. Keep the package's separator convention unless the component implementation is redesigned as a whole.

## Context and verification

Target compact inspection:

```bash
php artisan accelerator:context resource {resource}
php artisan accelerator:context resource {resource} --expand
php artisan accelerator:verify-resource {resource}
```

Default context does not execute field closures or instantiate schemas with a null record. The verifier is independent and enforces only registration, model, and policy invariants.

## Taste gate

Do not refactor the custom sidebar, login layout, or VerticalWizard without explicit user approval. Visual cleanup is Phase 2 work and requires current-state comparison plus acceptance on real pages.
