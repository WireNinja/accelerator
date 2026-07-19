---
name: accelerator-filament
description: Build Filament v5 resources the Accelerator way — BetterResource, ResourceEnum, Shield, DiscoverAsResource only, concentrated long form, BigDecimal, Lookup helper, and strict deprecation rules.
---

# Accelerator Filament

Authoritative source for Filament v5 conventions in Accelerator-powered apps. Mirrors `misc/llm/FILAMENT.md` shipped with pilot projects. Update this skill (not the userland file) so `php artisan boost:install` distributes the latest version.

## When To Use

Filament panels, resources, forms, tables, actions, widgets, policies, Shield permissions, BigDecimal columns, dependent fields, ResourceEnum changes, custom Filament components.

## Deprecation Notes (Filament v5)

Old patterns to avoid:

- `Action::form()` is deprecated — use `Action::schema()` for modal action fields.
- `ImageColumn::size()` is deprecated for image dimensions — use `ImageColumn::imageSize()`.
- `Placeholder` is deprecated — use `Filament\Infolists\Components\TextEntry` for read-only labelled values, `Filament\Schemas\Components\Text` for instructional text.
- `Filament\Tables\Actions\*` namespace — use `Filament\Actions\*` unless a table-only feature demands the old class.
- `Grid::make(12)` wrapping the entire form — use `->columns(12)` on the `Schema` / `Form` directly with `columnSpan` on each component.
- Excessive `Split` / `Stack` layouts in tables — keep flat columns so sort/filter work.

`phpstan/phpstan-deprecation-rules` is installed. Treat any old API usage as a design error to fix, not an opt-in warning.

### Strict enforcement

- Do NOT change a working Filament API just because it looks "more standard" (e.g. swapping `recordActions` for `actions`). Only change when this skill or `FILAMENT.md` lists it as deprecated.
- No over-engineering: if a need is met by Filament default behavior or a single string policy ability, do not add closures, hooks, wrappers, or layers.
- If you encounter an unfamiliar pattern that is not flagged here, ask the user before refactoring.

### `TextEntry` usage

- Use `TextEntry::make()` inside a `Schema` for read-only labelled values (summaries, simulations, derived states).
- For dynamic state, use `->state(fn (...) => ...)` plus a formatter (`->numeric()`, `->money()`, `->badge()`, `->html()`).
- For plain instructional text without label/value pairing, use `Text::make()`, not `TextEntry`.

## Authorization (Strict)

Security must not depend on UI visibility — use **policy-driven security**.

### Never use `visible()` / `hidden()` for permissions

```php
// Wrong:
Action::make('delete')->visible(fn () => auth()->user()->can('delete_user'))

// Right:
Action::make('delete')->authorize('delete')
```

State-based exclusive actions (`activate`/`deactivate`, `suspend`/`unsuspend`, `open`/`close`) belong in the policy method that returns `false` when state mismatches. `hidden()` as a substitute is over-engineering.

`hidden()` / `visible()` are only valid for non-authorization presentation that genuinely cannot be modelled in policy.

### `mustUser()` policy

- `mustUser()` is for flows that legitimately require an authenticated actor. Filament admin panel surfaces (resources, pages, actions, hooks) ALWAYS run authenticated, so use `mustUser()`.
- If `mustUser()` is declared to return `App\Models\User`, do NOT add local PHPDoc workarounds like `/** @var User $actor */` to satisfy static analysis.
- Use `user()?->...` only for genuinely unauthenticated surfaces (background jobs, CLI, seeders).
- In admin surfaces, `user()` is treated as an invariant violation unless the flow is non-admin.

### Safe policy patterns

- Block self-mutation (suspend/delete self) via `$authUser->id === $model->id` inside the policy.
- Super Admin can only be touched by another Super Admin.
- Use the backed `RoleEnum::SuperAdmin` directly in `hasRole()` — do not call `->value`.

## Shield & Permissions

### NEVER create policies manually

Do NOT run `php artisan make:policy` for Filament resources. Manually-created policies bypass `shield:safe-regenerate` formatting and end up returning `false` everywhere.

Single solution:

```bash
php artisan shield:safe-regenerate
```

This generates missing policies after a Resource is registered in `ResourceEnum`. To customise (e.g. block Super Admin deletion), edit the **generated** policy.

### No global permission pollution

Never add custom methods (like `suspend`, `unsuspend`, `impersonate`) to `policies.methods` in `config/filament-shield.php`. That makes the method appear on EVERY resource — a fatal architectural error.

### Per-resource permissions

Use `ResourceEnum::getResourcesPermissions()` to declare custom abilities ONLY where relevant, then wire it in `config/filament-shield.php` via `'manage' => \App\Enums\ResourceEnum::getResourcesPermissions()`.

### Mandatory steps when adding a resource

1. `php artisan make:filament-resource {Name}` — Filament native generator. Do NOT wrap or replace.
2. **Inject the trait** in the generated `{Name}Resource.php`: `use WireNinja\Accelerator\Filament\Traits\BetterResource;` then `use BetterResource;` inside the class body.
3. **Add the only allowed discovery attribute** above the resource class: `#[DiscoverAsResource(key: '{camelKey}', form: {Name}Form::class, table: {Names}Table::class)]`. Imports as needed: `WireNinja\Accelerator\Attributes\DiscoverAsResource`.
4. Register in `ResourceEnum` (case, label, resource, navigation icon `lucide-*`, navigation group, panel group, permissions).
5. Review the generated form/table/policy in `app/Filament/...` and `app/Policies/`.
6. Run `php artisan shield:safe-regenerate` (idempotent, safe to repeat).
7. Use strict namespaces: `Filament\Schemas\Components\Utilities\Get` / `Set`, `Filament\Schemas\Components\Tabs\Tab`. Never the legacy `Filament\Forms\Get`.
8. All static Eloquent calls must start with `->query()` (e.g. `Location::query()->whereIn(...)`) for strict analysis.
9. **Anti-bullshit closing gate** — run `php artisan accelerator:verify-resource {key} --compact`. Exit 0 + `"status":"PASS"` is the only acceptable signal. Critical checks: `BetterResource` trait, `#[DiscoverAsResource]` attribute, no bulk action leak, policy registered. Do NOT claim "selesai" without a clean PASS.
10. Final response when touching a resource MUST start with the markdown checklist below. Trigger the checklist whenever the user mentions `FILAMENT.md`, asks you to audit a resource, or you modify Filament code.

`accelerator:resource-context` payload is the primary summary. It must surface model, pages, relation managers, widgets, actions, and authorization (string ability or default policy hint). Bulk actions appearing in the payload are violations to clean, not capabilities to keep.

The scanner reads the configured `ResourceEnum` as its only registry. It no longer performs a second filesystem discovery pass or reports fake zero-count registries. `--compact` controls JSON whitespace; `--expand` adds component trees, relation forms, tabs, and source locations.

## Localization (Bahasa Indonesia)

All client-facing text uses professional Bahasa Indonesia.

- Labels: `Nama Lengkap`, `Alasan Penangguhan`, `Hapus`.
- Modal copy: `modalHeading`, `modalDescription`, `modalSubmitActionLabel` ("Apakah Anda yakin ingin menghapus data ini?").
- Notifications: `successNotificationTitle('Data berhasil dipulihkan')`.
- Placeholders: `Contoh: John Doe`, `Cari data...`.

## Icons & UI

- Lucide only — `lucide-user`, `lucide-shield-check`. Do NOT use `heroicon-` prefix except for Filament built-in status indicators.
- `Section` MUST have a relevant icon.
- `Tab` MUST have an icon.
- `BooleanCard` MUST have an icon depicting the "true" state.
- `RadioCards` icons come from the enum via `HasIcon`.
- Wrap row actions in `ActionGroup` so the UI stays clean.
- Destructive/sensitive actions MUST use `requiresConfirmation()`.
- Always provide an `emptyState` for tables.

## Table Standards

### Bulk actions are forbidden

Do NOT use any bulk action API. The system rejects bulk semantics for architectural, audit, and security reasons. Forbidden:

- `bulkActions()`, `pushBulkActions()`, `groupedBulkActions()`, `getBulkActions()`
- `BulkAction::make(...)`, `BulkActionGroup::make(...)`, `DeleteBulkAction::make()`
- Bulk semantics inside `toolbarActions()` or `headerActions()`
- Empty placeholders like `->bulkActions([])`

If a scanner / PHPStan / review finds any of those, it is an **architecture violation**.

### Default action labels

Do NOT set `->label('Edit')` on `EditAction::make()` or `->label('Hapus')` on `DeleteAction::make()`. Framework translation is sufficient and more maintainable.

### Empty state

Every table needs `emptyState`. Globals are already wired (`lucide-database` icon, "Belum ada data" heading, default description). Override only when a domain-specific copy improves UX. Provide `emptyStateActions([CreateAction::make()])` so the user can act immediately.

### Flat, complete, toggleable columns

- Main resource lists: prefer flat tables showing as many business columns as fit reasonably on one screen. Do NOT prematurely reduce to "safe" minimum.
- Primary columns (identity, status, location, related party, daily-decision signals) stay visible.
- Secondary/audit/context columns get `->toggleable()`. Rare ones use `->toggleable(isToggledHiddenByDefault: true)`.
- Toggleable columns respect the user's toggle state within the current browser session. Session persistence is OFF by default globally — do NOT assume toggles survive page reloads unless the project explicitly enables persistence.

### Enum metadata is automatic

If a model attribute is cast to an enum that implements `HasLabel`, `HasColor`, `HasIcon`, or `HasDescription`:

- Filament reads metadata automatically. For badge `TextColumn`, the default is just `->badge()`. Do NOT add `->color(fn (StatusEnum $state) => match (...))` that simply mirrors the enum.
- If a label/color/icon/description is missing, fix the **enum**, not the UI. Don't duplicate metadata across columns/forms/filters.
- `->options(MyEnum::class)` is the default for enum-backed fields. Don't manually build option arrays from `MyEnum::cases()` if the result is identical to enum metadata.
- For backed enums using `WireNinja\Accelerator\Concerns\BetterEnum`, do NOT compare with `===`, `!==`, raw strings, ordinals, `->value`, or `getRawOriginal()`. Use the helpers `->is(...)`, `->isNot(...)`, `->isAny(...)`, `->isNone(...)`.
- Forms/actions targeting enum-cast columns: pass enum cases, do not downgrade to `->value` unless required.
- UI closures (`hidden()`, `disabled()`, `visible()`, `icon()`, `color()`, `description()`, `formatStateUsing()`) MUST use the clean enum cast via `BetterEnum` helpers (e.g. `$record->status->is(StatusEnum::Closed)`).
- `match` statements duplicating enum metadata are code-quality violations.

### Action button anti-overengineering

OVER ENGINEERING is a rule violation, not a style preference. Default answer for an additional `Action::make(...)` (row, header, form, page) is **NO**. Before adding one, answer in order:

1. Can this be a regular form field (TextInput, Select, relationship, repeater, tab, section) or a default CRUD save?
2. Is this a single attribute change, simple relationship, or simple pivot that Filament native already handles?
3. Is this only being added so the resource-context scanner sees a particular ability? If yes, FIX THE METADATA, do not change UX.
4. Is this genuinely an explicit domain transition with side effects, confirmation needs, or lifecycle steps that don't belong in the form?

Only when (4) is yes does an extra action button become valid.

- Do NOT move parent-form interactions into header/page modals just to expose a policy verb.
- Do NOT add modal actions for trivial assign/sync/switch/update operations when a native form field suffices.
- Do NOT mirror table row-action lifecycle verbs (`close`, `reopen`, `markAsCurrent`) into `EditRecord` headers — pick one location.

### Authorization for actions

Default answer for new authorization abilities is **NO**, unless explicitly requested or domain-required.

- Use string abilities: `->authorize('update')`, `->authorize('suspend')`, `->authorize('reassignManager')`.
- Do NOT write `->authorize(fn ($record) => mustUser()->can(...))` if a string ability achieves the same.
- `CreateAction::make()`, `EditAction::make()`, `DeleteAction::make()` do NOT need redundant `->authorize(...)` if you just want default policy. Doing so anyway is over-engineering.
- Closure authorization is only valid when the policy method genuinely cannot express the rule.
- Filament forms/edit pages already wire to the resource policy. Do NOT add `->rule(...)` / `abort_if(...)` that re-asserts the same target-record restriction.
- Distinguish target-record restrictions (handled by policy) from current-user option-filtering (handled by `modifyQueryUsing`, options, validation).
- For mutually exclusive state actions (`activate`/`deactivate`), model the exclusivity in the respective policy method. NOT `hidden()`.

### Action class anti-overengineering

Default answer for a new action class is **NO**.

- Do NOT wrap simple `sync()`, `attach()`, `detach()`, `update()`, `save()` in an action class without a real domain invariant, multi-step transaction, sequencing, posting, audit side effect, or lifecycle transition.
- Do NOT create an action class just because the resource/page method "looks tidier" elsewhere — short, clear, single-step methods stay in place.
- Do NOT introduce boilerplate helpers (`getActor()`, `getXRecord()`, `reloadRecordAndForm()`) just to placate static analysis. Fix typing or model PHPDoc instead.
- Do NOT use `Location|int` union types when the contract really only needs the ID.
- Do NOT add `getRawOriginal()` or repeated manual casts to compensate for bad typing. Run `php artisan accelerator:model-doc {Model} --write`.
- Do NOT use `strval()`, `intval()`, `(string)`, `(int)` etc for application data flows. Use `WireNinja\Accelerator\Support\Cast` where boundary conversion is genuinely required.
- Do NOT call `CarbonImmutable::now(config('app.timezone'))` for normal flows — `app.timezone` is global. `CarbonImmutable::now()` is enough unless a non-default timezone is explicitly required.

### Business exception & Eloquent attributes

- Throw `WireNinja\Accelerator\Exceptions\BusinessException` for known business-rule failures (status lifecycle invalid, parent aggregate not ready, period closed, record already active, etc). NOT raw `Exception`, `RuntimeException`, or `DomainException`.
- Use normal Eloquent properties for reads and direct assignment, `fill()`, or `update()` for writes. Prefer `fill()` when one transition changes several attributes.
- For `BigDecimalCast` columns, do NOT recast model values via additional helpers. Use `$model->quantity` directly.
- For `BetterEnum`-cast columns, the property is the enum instance. Compare with `->is(...)`, `->isNot(...)`, `->isAny(...)`, `->isNone(...)`. Never downgrade to `->value` for comparison.
- After adding or changing a column, cast, relationship, or nullability, run `php artisan accelerator:model-doc ModelName --write` so model property PHPDoc stays in sync. Runtime behavior must never depend on PHPDoc.

### Relationship save hooks are an escape hatch

`saveRelationshipsUsing()` is advanced — NOT a default tool.

- Do NOT use it for standard relations that `->relationship(...)` already saves.
- If you need to block specific options on a relationship field, prefer `modifyQueryUsing`, options, or `->rule(...)`.
- If a reviewer asks "why isn't this just default?", that's the over-engineering alarm.

### Laravel super powers first

Before adding new classes/buttons/hooks/helpers, use Laravel + Filament built-ins: `Collection`, `Arr`, `Str`, `when()`, `unless()`, `tap()`, `filled()`, `blank()`, Eloquent relationship methods, casts, scopes, observers, and Filament native relationship/form components. One clear Eloquent-plus-Collection chain beats three layers of wrappers.

### Repeater preference for compact relations

For `HasMany` / `MorphMany` with few fields that should validate alongside the parent record, prefer `Repeater::make()->relationship()` inside the resource form instead of a Relation Manager. Use `Repeater` `table()` mode with `compact()`, `itemNumbers()`, `addActionLabel()`, and cross-row validation (e.g. unique combinations) for fast scan/edit UX. Use Relation Manager only when child records need a full interactive table, separate actions, heavy filters, or many columns.

## Global Configuration (Don't Repeat)

Accelerator already wires global defaults in its Filament provider. Do NOT redefine locally:

- `Select::searchable()->preload()->native(false)` — already global.
- `DateTimePicker::native(false)->displayFormat('j F Y H:i')` — already global.
- `TimePicker::native(false)->displayFormat('H:i')` — already global.
- `FileUpload::imageEditor()->maxParallelUploads(5)->maxSize(100MB)` — already global; the exact limit comes from `accelerator.uploads.max_megabytes`.
- Table defaults: cursor pagination, IDR currency, id-ID locale, defer loading/filters/columns, default sort `id desc`, striped, empty state — all global. Session persistence is OFF by default (all `persistXxxInSession(false)`).

Override locally only when intentionally diverging, with a short comment explaining why.

## BigDecimal Inputs (Required)

For columns cast with `WireNinja\Accelerator\Database\Casts\BigDecimalCast`:

- Do NOT use `TextInput::numeric()`. It mishandles precision via Livewire round-tripping.
- Use `WireNinja\Accelerator\Filament\Components\SeparatedNumberInput` (aligns with `BigDecimalSynth`):

```php
use WireNinja\Accelerator\Filament\Components\SeparatedNumberInput;

SeparatedNumberInput::make('discount_percent_recommendation', precision: 4)
    ->label('Rekomendasi Diskon (%)')
    ->required();
```

Rule: model cast BigDecimal → form uses `SeparatedNumberInput`.

The default separators are intentionally US-style (`5,000.00`). DO NOT invert them — the strip pipeline will silently corrupt input. See the `@DONOT-REMOVE` block on the component.

## Form Design (Concentrated Long Form)

Project-wide style: high-density vertical stacking. Forms must not look thin or sprawl uncontrollably wide.

### Grid

1. Root schema: `->columns(12)` always.
2. Main `Section`: `->columnSpan(8)` — vertical, centered concentration.
3. Inside section: `columns(1)` or `columns(2)` based on density.
4. Sections with a single dominant input (Address, Internal Notes) MUST use `->hiddenLabel()` on that input to avoid visual redundancy with the section heading.

### Vertical rhythm

Card-based components (`BooleanCard`, `AdvancedRadioCards`, `AdvancedCheckboxCards`) MUST NOT sit inline with `TextInput`/`Select` in the same row. Place them on:

- their own row with `columnSpanFull()`, OR
- a dedicated section, OR
- the bottom of a section after standard inputs finish.

### Copy

- Every `TextInput`, `Textarea`, and free-input `Select` MUST have a descriptive `placeholder` (e.g. `Contoh: Nama Skema Harga 2024`).
- Section descriptions are full professional sentences with capital first letter and trailing period.
- Use `Callout` for critical/multi-step instructions. Don't stuff long copy into `helperText`.

### Performance

- Use `static fn` on every Filament closure (`hidden()`, `dehydrated()`, `options()`, `default()`, etc).
- Delegate label/color/icon to enums via `HasLabel`, `HasColor`, `HasIcon`. Don't duplicate `match` statements at the UI layer.

## AI Discovery & Token Budget

```bash
php artisan agent:resource-context user --compact
php artisan agent:resource-context product --compact
php artisan agent:resource-context product --compact --expand
php artisan agent:resource-context --list --compact
```

- `--compact` is default for AI agents (token-friendly).
- `--expand` overrides minification when full tree is needed.
- Bulk actions surfacing in the payload are violations to clean.

## Reference Files

Use these as the actual source of truth — do NOT copy older examples:

- `app/Filament/Resources/Users/UserResource.php` — thin resource delegating to form/table/pages.
- `app/Filament/Resources/Users/Tables/UsersTable.php` — empty state, flat primary columns, toggleable secondary, `ActionGroup`, default Edit/Delete actions, custom actions with string `->authorize('ability')`.
- `app/Filament/Resources/Users/Schemas/UserForm.php` — tabs, sections, default-when-sufficient relationship fields.

## Tabs & Stats Widgets

Tabs and stat widgets are NOT mandatory defaults. Use them only when they add value:

- Data is large enough that fast segmentation helps.
- State is clear and stable (e.g. active vs suspended).
- Users genuinely need quick summaries without manual filtering.

Standards:

- Widget queries are real, not dummy.
- Tab labels are Bahasa Indonesia with relevant icons.
- `modifyQueryUsing()` on a tab is simple and explicit.
- For small or weakly-segmented resources, do NOT add stats/tabs just for completeness — that's over-engineering.

## Lookup Helper

For dependent fields (cascading select), use `WireNinja\Accelerator\Support\Filament\Lookup`:

```php
use WireNinja\Accelerator\Support\Filament\Lookup;

->options(static fn (Get $get) => Lookup::pluck($get, 'parent_field_name')
    ->model(RelatedModel::class)
    ->label('nama_kolom_label')
    ->value('nama_kolom_id')
    ->modifyQuery(fn ($query) => $query->active())
    ->get()
)
```

Why required:

1. No more `if (empty($ids)) return []` boilerplate.
2. Consistent dependency-handling logic across the app.
3. Centralised place to add global filter rules later.

`Lookup` hardcodes `whereIn('id', ...)` because the project convention is always integer `id` primary keys. If the project ever drops that convention, the helper itself must be updated — not the call sites.

## VerticalWizard (Special Case)

Project ships `WireNinja\Accelerator\Filament\Schemas\Components\VerticalWizard` — vertical-tab navigation with off-white base card and elevated active pill. Use this ONLY when the user explicitly asks. Most operational forms work better with the Concentrated Long Form (stacked sections).

```php
use WireNinja\Accelerator\Filament\Schemas\Components\VerticalWizard;
use Filament\Schemas\Components\Wizard\Step;

VerticalWizard::make([
    Step::make('Langkah Pertama')->icon('lucide-box')->schema([/* ... */]),
    Step::make('Langkah Kedua')->icon('lucide-settings')->schema([/* ... */]),
])
    ->navigationHeading('Judul Navigasi Kiri')
    ->navigationDescription('Deskripsi panduan di atas navigasi tab.')
    ->sticky(false)
    ->skippable()
    ->columnSpanFull();
```

## Resource Verification Checklist

When you finish or audit a resource (or the user mentions `FILAMENT.md`), the response MUST end with the `accelerator:verify-resource` JSON output:

```bash
php artisan accelerator:verify-resource {key} --compact
```

Exit 0 + `"status":"PASS"` is the only acceptable signal of completion. If FAIL, list the findings and explain how each is being addressed before claiming completion.

The command checks the four critical, non-negotiable rules from this skill:

1. `BetterResource` trait used on the resource class
2. `#[DiscoverAsResource]` attribute present
3. No bulk action API leak (`BulkAction` / `BulkActionGroup` / `toolbarActions`)
4. Policy class registered (run `shield:safe-regenerate` after `ResourceEnum` registration)

Best-practice items — split form/table classes, empty state actions, BooleanCard over Toggle, `static fn`, Bahasa Indonesia labels, action label removal — are reviewed in code, not gated by the command. They remain mandatory by skill, but they are not part of the JSON gate.

Last line of the response MUST ask: *"Apakah saya (atau Anda) sudah mengikuti seluruh standar dan aturan di file `FILAMENT.md` ini termasuk `ResourceEnum` dan menjalankan regenerasi Shield?"*

---

**Note**: keep this skill in sync with `misc/llm/FILAMENT.md` shipped to pilot projects. Skill is the upstream — userland file is the consumed convention. Run `php artisan boost:install` in pilot projects to get updates.
