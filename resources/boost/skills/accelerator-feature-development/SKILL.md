---
name: accelerator-feature-development
description: Build or refactor an Accelerator business feature end to end from requirements, database schema, Eloquent model, policies and Shield RBAC through Filament resources, domain Actions or Services, activity logging, worker safety, and verification. Use whenever implementing business CRUD, a domain workflow or state transition, a new model-backed Filament feature, or custom application logic spanning more than one layer.
---

# Accelerator Feature Development

Ship the smallest complete business slice. Keep ordinary CRUD native; add architecture only where a real invariant, transaction, side effect, or reusable capability requires it.

## Workflow

1. Define the business outcome, actors, permissions, states, invariants, side effects, and expected failures. Ask only when a missing choice changes those materially.
2. Inspect installed versions, sibling conventions, relevant schema/model/policy/resource, and version-specific documentation. Activate `accelerator-model-context`, `accelerator-filament`, `accelerator-activity-log`, and relevant Laravel domain skills as needed.
3. Design the schema around real query paths. Add foreign keys, uniqueness, nullability, precision, and indexes deliberately. Never edit a deployed migration; create a forward migration.
4. Create or update the model with explicit casts, typed relationships, useful local scopes, and defaults matching the database. Use Accelerator's BigDecimal stack for money or precision-sensitive values.
5. Run the migration before schema-driven Filament generation. Native `make:filament-resource --generate` requires an existing migrated table; never combine `--generate` with `--migration`.
6. Generate the resource through native `make:filament-resource`, configure its navigation metadata, then run `shield:safe-regenerate --panel={panel}`.
7. Complete the resource using native Filament fields, relationships, actions, and policies. Keep security in policies; UI visibility is not authorization.
8. Add activity logging when business history matters. Allowlist attributes and snapshot configured relationship changes.
9. Verify the complete slice through direct source inspection, static analysis, formatting, and the affected authenticated UI.

## Architecture boundary

- Keep simple field persistence and native CRUD in Filament's normal resource/page flow.
- Use a named Action for one business operation or state transition such as `ApproveOrder`, `ReceivePayment`, or `TransferStock`.
- Use a Service for a reusable capability such as pricing, inventory calculation, document generation, or an external integration.
- Do not create a Service, repository, DTO, interface, event, or job mechanically for every model.
- Use constructor injection in application classes. Do not call `app()` or `resolve()` inside Actions or Services.
- Add an interface only at a real external or replaceable boundary, not one interface per concrete class.
- Put multi-write invariants inside `DB::transaction()`. Use database constraints, atomic updates, `lockForUpdate()`, or `Cache::lock()` when races are possible.
- Make retryable operations idempotent. Dispatch durable or retryable side effects after commit; use `defer()` only for non-critical work that may be lost on process failure.
- Throw `BusinessException` for expected business-rule rejection. Let unexpected failures remain reportable defects.

## Filament and Shield

- Use `shield:safe-regenerate` after native resource generation; do not call destructive vendor generation shortcuts.
- Keep standard CRUD abilities policy-driven. Declare custom string abilities for non-CRUD domain actions.
- Preserve Super Admin access and protect unsafe self-mutation or privileged targets explicitly.
- Bulk actions are disabled by default. Add one only for an explicit business need with policy checks, per-record safety, auditability, and bounded workload.
- Follow Accelerator global form/table defaults and override them locally when cardinality or UX requires it.

## Runtime invariant

HTTP requests run through PHP-FPM, while queue and scheduler commands may remain alive for a bounded interval.

- Never keep request, authenticated user, tenant, mutable model, or other request-derived state in static properties or singletons.
- Use scoped bindings for request-scoped services and resolve `request()` or authentication inside the method that needs it.
- Register listeners once in service providers, not inside request handlers.
- Reset unavoidable static caches through the appropriate lifecycle; do not append request data indefinitely.
- Pass scalar identifiers to queued or concurrent work and re-fetch models inside the worker.
- Restart/reload the stage after code, provider, environment, or configuration changes through Accelerator's stage-scoped commands.

## Verification

Use the narrowest relevant set:

```bash
php artisan migrate:status
php artisan accelerator:context model {Model}
vendor/bin/pint --dirty --format agent
composer phpstan
```

Exercise the affected authenticated resource and custom action. Existing relevant tests may run; do not create, modify, or delete test files unless the owner explicitly requests tests in the current prompt.
