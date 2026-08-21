---
name: accelerator-filament
description: Build or review Accelerator Filament v5 panels, resources, forms, tables, actions, policies, Shield permissions, BigDecimal inputs, and custom components. Use for all Filament UI and authorization work.
---

# Accelerator Filament

## Workflow

1. Inspect the registered Filament panel/resource, model, policy, form, and table source.
2. Search version-specific Filament documentation before changing an unfamiliar API.
3. Prefer native Filament/Laravel behavior; add package abstractions only for repeated real invariants.
4. Make authorization policy-driven; UI visibility is not security.
5. Run static analysis, cache the views, and exercise the affected authenticated page.

For an end-to-end model-backed business feature, activate `accelerator-feature-development`; this skill owns Filament-specific implementation details, not the whole domain workflow.

## Resource truth

Filament's registered panel/resource is authoritative. Use Filament's native `make:filament-resource` command for scaffolding. Navigation groups come from app-owned `App\Enums\System\NavigationGroup`; keep each resource icon, label, policy, and panel placement in the resource itself. Do not recreate `BetterResource` or a resource registry enum.

Run migrations before using `make:filament-resource --generate`. Schema-driven generation requires the current database table and may not be combined with `--migration`. After generation, configure navigation metadata in the resource, then run `shield:safe-regenerate --panel={panel}`.

Custom Shield abilities belong in a permission-specific declaration, not navigation metadata. Super Admin bypass/access must remain valid after Shield regeneration.

## Authorization

- Use policies for resources and string abilities for actions.
- Do not use `visible()`/`hidden()` as permission checks.
- Avoid redundant `authorize()` on native CRUD actions already covered by policy.
- Put mutually exclusive state rules in policy methods.
- Block unsafe self-mutation and protect Super Admin targets explicitly.
- Run `shield:safe-regenerate` only through the Accelerator-safe workflow.

Custom record actions must name the corresponding policy ability explicitly. Filament resolves the current Eloquent record for the policy call:

```php
use App\Actions\PublishPost;
use App\Models\Post;
use Filament\Actions\Action;

Action::make('publish')
    ->authorize('publish')
    ->requiresConfirmation()
    ->action(fn (Post $record, PublishPost $publishPost): mixed => $publishPost->handle($record));
```

The model policy owns both actor permissions and record-state rules:

```php
public function publish(User $user, Post $post): bool
{
    return $user->can('Publish:Post') && $post->isDraft();
}
```

Built-in resource actions such as `CreateAction`, `EditAction`, and `DeleteAction` read their standard policy methods automatically. `visible()` and `hidden()` may still control presentation for non-security reasons, but they never replace `authorize()` on a custom action.

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

## Verification

Inspect the resource, page, form, table, model, and policy source directly. Confirm the resource is registered in the intended panel, run `shield:safe-regenerate --panel={panel}`, Pint, PHPStan, and `php artisan view:cache`, then exercise the affected page and custom actions as users with allowed and denied roles.

`accelerator:context resource` and `accelerator:verify-resource` are legacy compatibility commands. Do not use them as development workflow or completion gates.

## Taste gate

Preserve Filament's native sidebar and topbar. The package panel switcher belongs in a native topbar render hook and stays hidden when only one panel is accessible. Preserve VerticalWizard unless the owner explicitly changes product taste. Login uses Filament's native layout with the minimal Accelerator login class and OAuth render hook; do not recreate alternative login layouts.

The `TOPBAR_END` dual-stage environment badge is a safety invariant, not decoration. Keep it hidden for single-stage deployments and visible locally, on staging, and on production when dual topology is enabled. Default labels are `LOCAL DATA`, `TEST DATA`, and `LIVE DATA`. Labels and Filament badge colors come from `accelerator.ui.environment_indicator`.
