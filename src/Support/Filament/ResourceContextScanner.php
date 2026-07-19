<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Filament;

use BackedEnum;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;
use UnitEnum;
use WireNinja\Accelerator\Attributes\DiscoverAsResource;
use WireNinja\Accelerator\Enums\Concerns\MustBeResourceEnum;
use WireNinja\Accelerator\Filament\Traits\BetterResource;

final class ResourceContextScanner
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function scan(
        ?string $resource = null,
        bool $includeRegistry = false,
        bool $expand = false,
    ): array {
        $catalog = $this->catalog();
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'summary' => $this->catalogSummary($catalog),
        ];

        if (blank($resource)) {
            $payload['registry'] = $this->describeRegistry($catalog);

            return $payload;
        }

        $resourceClass = $this->resolveResourceClass((string) $resource, $catalog);
        $payload['resource'] = $this->describeResource(
            $resourceClass,
            $catalog['resources'][$resourceClass],
            $catalog,
            $expand,
        );
        $payload['summary']['requested_resource'] = $resource;
        $payload['summary']['resolved_resource'] = $resourceClass;

        if ($includeRegistry) {
            $payload['registry'] = $this->describeRegistry($catalog);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $enumClass = config('accelerator.enums.resource');

        if (! is_string($enumClass) || ! enum_exists($enumClass) || ! is_subclass_of($enumClass, MustBeResourceEnum::class)) {
            throw new RuntimeException('Configure accelerator.enums.resource with an enum that implements MustBeResourceEnum.');
        }

        /** @var class-string<UnitEnum&MustBeResourceEnum> $enumClass */
        $resources = [];
        $lookup = [];
        $ambiguousAliases = [];
        $diagnostics = [];

        foreach ($enumClass::cases() as $case) {
            $resourceClass = $case->getResource();

            if (! class_exists($resourceClass) || ! is_subclass_of($resourceClass, Resource::class)) {
                $diagnostics[] = [
                    'type' => 'invalid_registered_resource',
                    'enum_case' => $case->name,
                    'class' => $resourceClass,
                ];

                continue;
            }

            /** @var class-string<resource> $resourceClass */
            $attribute = $this->resourceAttribute($resourceClass);
            $pages = $this->pageClasses($resourceClass);
            $relations = $this->relationManagerClasses($resourceClass);
            $entry = [
                'class' => $resourceClass,
                'enum_case' => $case->name,
                'key' => $attribute?->key ?: $this->defaultResourceKey($resourceClass),
                'managed' => $attribute !== null,
                'form' => $attribute?->form,
                'table' => $attribute?->table,
                'policy' => $attribute?->policy,
                'model' => $resourceClass::getModel(),
                'panel' => $case->getPanelGroup(),
                'pages' => $pages,
                'relations' => $relations,
            ];
            $resources[$resourceClass] = $entry;

            foreach ($this->resourceAliases($entry) as $alias) {
                if (isset($ambiguousAliases[$alias])) {
                    continue;
                }

                if (isset($lookup[$alias]) && $lookup[$alias] !== $resourceClass) {
                    if (in_array($alias, $this->requiredUniqueAliases($entry), true)) {
                        $diagnostics[] = [
                            'type' => 'duplicate_resource_alias',
                            'alias' => $alias,
                            'resources' => [$lookup[$alias], $resourceClass],
                        ];
                    } else {
                        unset($lookup[$alias]);
                        $ambiguousAliases[$alias] = true;
                    }

                    continue;
                }

                $lookup[$alias] = $resourceClass;
            }
        }

        ksort($resources);
        ksort($lookup);

        return $this->catalog = [
            'enum' => $enumClass,
            'resources' => $resources,
            'lookup' => $lookup,
            'permissions' => $enumClass::getResourcesPermissions(),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, int>
     */
    private function catalogSummary(array $catalog): array
    {
        $resources = $catalog['resources'];

        return [
            'resources_registered' => count($resources),
            'resources_managed' => count(array_filter($resources, static fn (array $entry): bool => $entry['managed'])),
            'external_resources' => count(array_filter($resources, static fn (array $entry): bool => ! $entry['managed'])),
            'forms_linked' => count(array_filter($resources, static fn (array $entry): bool => filled($entry['form']))),
            'tables_linked' => count(array_filter($resources, static fn (array $entry): bool => filled($entry['table']))),
            'pages_registered' => array_sum(array_map(static fn (array $entry): int => count($entry['pages']), $resources)),
            'relation_managers_registered' => array_sum(array_map(static fn (array $entry): int => count($entry['relations']), $resources)),
            'registry_diagnostics' => count($catalog['diagnostics']),
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private function describeRegistry(array $catalog): array
    {
        $resources = [];

        foreach ($catalog['resources'] as $entry) {
            $policy = $this->resolvePolicyClass($entry['model'], $entry['policy']);
            $resources[] = $this->withoutEmpty([
                'key' => $entry['key'],
                'class' => $entry['class'],
                'enum_case' => $entry['enum_case'],
                'panel' => $entry['panel'],
                'managed' => $entry['managed'],
                'model' => $entry['model'],
                'form' => $entry['form'],
                'table' => $entry['table'],
                'policy' => $policy,
                'pages' => $entry['pages'],
                'relation_managers' => $entry['relations'],
            ]);
        }

        usort($resources, static fn (array $left, array $right): int => [$left['panel'], $left['key']] <=> [$right['panel'], $right['key']]);

        return [
            'enum' => $catalog['enum'],
            'resources' => $resources,
            'diagnostics' => $catalog['diagnostics'],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private function describeResource(
        string $resourceClass,
        array $entry,
        array $catalog,
        bool $expand,
    ): array {
        $modelClass = $entry['model'];
        $model = $this->makeModel($modelClass);
        $policyClass = $this->resolvePolicyClass($modelClass, $entry['policy']);
        $pages = $this->describePages($entry['pages'], $policyClass, $expand);
        $host = $this->resourceHost($entry['pages']);
        $form = $this->describeResourceForm($resourceClass, $entry['form'], $host, $expand);
        $table = $this->describeResourceTable($resourceClass, $entry['table'], $host, $policyClass);
        $relations = $this->describeRelations(
            $resourceClass::getRelations(),
            $modelClass,
            $this->firstPageClass($entry['pages']),
            $policyClass,
            $expand,
        );
        $surfacedAbilities = array_values(array_unique([
            ...$this->abilitiesFromPages($pages),
            ...$this->abilitiesFromActions($table['header_actions'] ?? []),
            ...$this->abilitiesFromActions($table['record_actions'] ?? []),
            ...$this->abilitiesFromActions($table['empty_state_actions'] ?? []),
        ]));
        sort($surfacedAbilities);

        $payload = [
            'class' => $resourceClass,
            'key' => $entry['key'],
            'enum_case' => $entry['enum_case'],
            'panel' => $entry['panel'],
            'managed' => $entry['managed'],
            'registered_in_resource_enum' => true,
            'discovery' => [
                'annotated_as_resource' => $this->attributePayload($this->resourceAttribute($resourceClass)),
            ],
            'model' => [
                'class' => $modelClass,
                'table' => $model?->getTable(),
            ],
            'navigation' => $this->navigation($resourceClass),
            'authorization' => $this->withoutEmpty([
                'policy' => $policyClass ? [
                    'class' => $policyClass,
                    'abilities' => $this->policyAbilities($policyClass),
                ] : null,
                'registered_abilities' => $catalog['permissions'][$resourceClass] ?? [],
                'surfaced_action_abilities' => $surfacedAbilities,
            ]),
            'pages' => $pages,
            'widgets' => $this->widgetsFromPages($pages),
            'form' => $form,
            'table' => $table,
            'relation_managers' => $relations,
            'diagnostics' => $this->resourceDiagnostics($resourceClass, $entry, $table),
        ];

        if ($expand) {
            $payload['source'] = $this->classSource($resourceClass);
            $payload['model']['source'] = $this->classSource($modelClass);
        }

        return $payload;
    }

    /**
     * @param  array<string, class-string>  $pages
     */
    private function resourceHost(array $pages): Component&HasSchemas&HasTable
    {
        $pageClass = $this->firstListPageClass($pages) ?? $this->firstPageClass($pages);

        if ($pageClass === null) {
            throw new RuntimeException('The resource has no registered page that can host schema/table introspection.');
        }

        $host = $this->app->make($pageClass);

        if (! $host instanceof Component || ! $host instanceof HasSchemas || ! $host instanceof HasTable) {
            throw new RuntimeException("Resource page [{$pageClass}] does not implement HasSchemas and HasTable.");
        }

        return $host;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeResourceForm(
        string $resourceClass,
        ?string $definitionClass,
        Component&HasSchemas $host,
        bool $expand,
    ): array {
        try {
            return $this->describeSchema(
                $resourceClass::form(Schema::make($host)),
                $definitionClass,
                $expand,
            );
        } catch (Throwable $throwable) {
            return [
                'definition_class' => $definitionClass,
                'introspection_error' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describeResourceTable(
        string $resourceClass,
        ?string $definitionClass,
        Component&HasTable $host,
        ?string $policyClass,
    ): array {
        try {
            return $this->describeTable(
                $resourceClass::table(Table::make($host)),
                $definitionClass,
                $policyClass,
            );
        } catch (Throwable $throwable) {
            return [
                'definition_class' => $definitionClass,
                'introspection_error' => $throwable->getMessage(),
                'violations' => $this->sourceTableViolations($definitionClass),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describeSchema(Schema $schema, ?string $definitionClass, bool $expand): array
    {
        $components = $schema->getFlatComponents(true);
        $fields = [];

        foreach ($components as $component) {
            $name = $this->normalize($this->callPublic($component, 'getName'));

            if (! is_string($name) || $name === '') {
                continue;
            }

            $fields[] = $this->withoutEmpty([
                'name' => $name,
                'type' => class_basename($component),
                'relationship' => $this->normalize($this->callPublic($component, 'getRelationshipName')),
            ]);
        }

        $payload = [
            'definition_class' => $definitionClass,
            'summary' => [
                'components' => count($components),
                'types' => $this->typeCounts($components),
                'named_fields' => count($fields),
            ],
            'fields' => $fields,
        ];

        if ($expand) {
            $payload['tree'] = array_map(
                fn (object $component): array => $this->schemaComponent($component),
                $schema->getComponents(true),
            );
            $payload['source'] = $definitionClass ? $this->classSource($definitionClass) : null;
        }

        return $this->withoutEmpty($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaComponent(object $component): array
    {
        $payload = $this->withoutEmpty([
            'type' => class_basename($component),
            'name' => $this->normalize($this->callPublic($component, 'getName')),
            'relationship' => $this->normalize($this->callPublic($component, 'getRelationshipName')),
        ]);
        $children = [];

        foreach ($this->callPublic($component, 'getChildSchemas') ?? [] as $childSchema) {
            if (! $childSchema instanceof Schema) {
                continue;
            }

            foreach ($childSchema->getComponents(true) as $child) {
                $children[] = $this->schemaComponent($child);
            }
        }

        if ($children !== []) {
            $payload['children'] = $children;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeTable(Table $table, ?string $definitionClass, ?string $policyClass): array
    {
        $columns = array_values($table->getColumns());
        $filters = array_values($table->getFilters());
        $headerActions = array_values($table->getHeaderActions());
        $recordActions = array_values($table->getRecordActions());
        $toolbarActions = array_values($table->getToolbarActions());
        $emptyStateActions = $this->callPublic($table, 'getEmptyStateActions');
        $emptyStateActions = is_array($emptyStateActions) ? array_values($emptyStateActions) : [];
        $violations = $this->sourceTableViolations($definitionClass);

        if ($toolbarActions !== []) {
            $violations[] = [
                'key' => 'toolbar_actions_runtime',
                'message' => 'Toolbar actions are forbidden by the Accelerator Filament contract.',
            ];
        }

        foreach ([...$headerActions, ...$recordActions, ...$toolbarActions] as $action) {
            if (Str::contains(class_basename($action), 'BulkAction')) {
                $violations[] = [
                    'key' => 'bulk_action_runtime',
                    'message' => 'A bulk action object is registered on the table.',
                ];
            }
        }

        return $this->withoutEmpty([
            'definition_class' => $definitionClass,
            'summary' => [
                'columns' => count($columns),
                'filters' => count($filters),
                'header_actions' => count($headerActions),
                'record_actions' => count($recordActions),
            ],
            'columns' => array_map($this->namedObject(...), $columns),
            'filters' => array_map($this->namedObject(...), $filters),
            'header_actions' => array_map(
                fn (object $action): array => $this->action($action, $policyClass),
                $headerActions,
            ),
            'record_actions' => array_map(
                fn (object $action): array => $this->action($action, $policyClass),
                $recordActions,
            ),
            'empty_state_actions' => array_map(
                fn (object $action): array => $this->action($action, $policyClass),
                $emptyStateActions,
            ),
            'violations' => $this->uniqueViolations($violations),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function namedObject(object $object): array
    {
        return $this->withoutEmpty([
            'name' => $this->normalize($this->callPublic($object, 'getName')),
            'type' => class_basename($object),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function action(object $action, ?string $policyClass): array
    {
        $payload = $this->withoutEmpty([
            'name' => $this->normalize($this->callPublic($action, 'getName')),
            'type' => class_basename($action),
            'abilities' => $this->actionAbilities($action),
        ]);

        if ($action instanceof ActionGroup) {
            $payload['actions'] = array_map(
                fn (object $child): array => $this->action($child, $policyClass),
                $action->getActions(),
            );
        }

        if ($policyClass !== null && ($payload['abilities'] ?? []) !== []) {
            $payload['policy'] = $policyClass;
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function actionAbilities(object $action): array
    {
        $authorization = $this->protectedProperty($action, 'authorization');
        $abilities = [];

        if (is_array($authorization)) {
            foreach ($authorization['abilities'] ?? [] as $ability) {
                $ability = $this->normalize($ability);

                if (is_string($ability) && $ability !== '') {
                    $abilities[] = $ability;
                }
            }
        }

        if ($abilities === []) {
            $defaultAbility = match (class_basename($action)) {
                'CreateAction' => 'create',
                'EditAction' => 'update',
                'DeleteAction' => 'delete',
                'ViewAction' => 'view',
                'ReplicateAction' => 'replicate',
                'RestoreAction' => 'restore',
                'ForceDeleteAction' => 'forceDelete',
                'AttachAction' => 'attach',
                'DetachAction' => 'detach',
                'AssociateAction' => 'associate',
                'DissociateAction' => 'dissociate',
                default => null,
            };

            if ($defaultAbility !== null) {
                $abilities[] = $defaultAbility;
            }
        }

        sort($abilities);

        return array_values(array_unique($abilities));
    }

    /**
     * @param  array<string, class-string>  $pages
     * @return list<array<string, mixed>>
     */
    private function describePages(array $pages, ?string $policyClass, bool $expand): array
    {
        $payload = [];

        foreach ($pages as $key => $pageClass) {
            $page = [
                'key' => $key,
                'class' => $pageClass,
                'kind' => $this->pageKind($pageClass),
                'header_actions' => [],
                'widgets' => [],
            ];

            try {
                $instance = $this->app->make($pageClass);
                $actions = $this->callAnyVisibility($instance, 'getHeaderActions');
                $page['header_actions'] = is_array($actions)
                    ? array_map(fn (object $action): array => $this->action($action, $policyClass), array_values($actions))
                    : [];
                $page['widgets'] = [
                    ...$this->widgetReferences($this->callAnyVisibility($instance, 'getHeaderWidgets'), 'header'),
                    ...$this->widgetReferences($this->callAnyVisibility($instance, 'getFooterWidgets'), 'footer'),
                ];

                if ($expand) {
                    $tabs = $this->callPublic($instance, 'getTabs');
                    $page['tabs'] = is_array($tabs) ? array_keys($tabs) : [];
                }
            } catch (Throwable $throwable) {
                $page['introspection_error'] = $throwable->getMessage();
            }

            if ($expand) {
                $page['source'] = $this->classSource($pageClass);
            }

            $payload[] = $this->withoutEmpty($page);
        }

        return $payload;
    }

    /**
     * @return list<array{class: string, placement: string}>
     */
    private function widgetReferences(mixed $widgets, string $placement): array
    {
        if (! is_array($widgets)) {
            return [];
        }

        $payload = [];

        foreach ($widgets as $widget) {
            if (is_string($widget)) {
                $payload[] = ['class' => $widget, 'placement' => $placement];
            } elseif ($widget instanceof WidgetConfiguration) {
                $payload[] = ['class' => $widget->widget, 'placement' => $placement];
            }
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return list<array<string, mixed>>
     */
    private function widgetsFromPages(array $pages): array
    {
        $widgets = [];

        foreach ($pages as $page) {
            foreach ($page['widgets'] ?? [] as $widget) {
                $key = $widget['class'].'@'.$widget['placement'];
                $widgets[$key] = $widget + ['page' => $page['class']];
            }
        }

        return array_values($widgets);
    }

    /**
     * @param  array<int, mixed>  $relations
     * @return list<array<string, mixed>>
     */
    private function describeRelations(
        array $relations,
        string $ownerModelClass,
        ?string $pageClass,
        ?string $policyClass,
        bool $expand,
    ): array {
        $payload = [];

        foreach ($relations as $relation) {
            if ($relation instanceof RelationGroup) {
                $payload[] = [
                    'kind' => 'group',
                    'label' => $this->normalize($this->callPublic($relation, 'getLabel')),
                    'managers' => $this->describeRelations(
                        $relation->getManagers(),
                        $ownerModelClass,
                        $pageClass,
                        $policyClass,
                        $expand,
                    ),
                ];

                continue;
            }

            $managerClass = null;
            $properties = [];

            if ($relation instanceof RelationManagerConfiguration) {
                $managerClass = $relation->relationManager;
                $properties = $relation->getProperties();
            } elseif (is_string($relation)) {
                $managerClass = $relation;
            }

            if (! is_string($managerClass) || ! is_subclass_of($managerClass, RelationManager::class)) {
                $payload[] = ['kind' => 'unknown', 'type' => get_debug_type($relation)];

                continue;
            }

            $payload[] = $this->describeRelationManager(
                $managerClass,
                $properties,
                $ownerModelClass,
                $pageClass,
                $policyClass,
                $expand,
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function describeRelationManager(
        string $managerClass,
        array $properties,
        string $ownerModelClass,
        ?string $pageClass,
        ?string $policyClass,
        bool $expand,
    ): array {
        $payload = [
            'kind' => 'manager',
            'class' => $managerClass,
            'relationship' => $this->callStatic($managerClass, 'getRelationshipName'),
            'properties' => $properties,
        ];

        try {
            /** @var RelationManager $instance */
            $instance = $this->app->make($managerClass);
            $instance->ownerRecord = $this->makeModel($ownerModelClass) ?? new $ownerModelClass;
            $instance->pageClass = $pageClass;
            $payload['table'] = $this->describeTable(
                $instance->table(Table::make($instance)),
                $managerClass,
                $policyClass,
            );

            if ($expand) {
                $payload['form'] = $this->describeSchema(
                    $instance->form(Schema::make($instance)),
                    $managerClass,
                    true,
                );
                $payload['source'] = $this->classSource($managerClass);
            }
        } catch (Throwable $throwable) {
            $payload['introspection_error'] = $throwable->getMessage();
        }

        return $this->withoutEmpty($payload);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $table
     * @return list<array<string, mixed>>
     */
    private function resourceDiagnostics(string $resourceClass, array $entry, array $table): array
    {
        if (! $entry['managed']) {
            return [[
                'type' => 'external_resource',
                'message' => 'External resources are registered but are not subject to Accelerator metadata rules.',
            ]];
        }

        $diagnostics = [];

        if (! in_array(BetterResource::class, class_uses_recursive($resourceClass), true)) {
            $diagnostics[] = ['type' => 'missing_better_resource_trait'];
        }

        foreach (['form', 'table'] as $key) {
            if (! is_string($entry[$key]) || ! class_exists($entry[$key])) {
                $diagnostics[] = [
                    'type' => "invalid_{$key}_link",
                    'class' => $entry[$key],
                ];
            }
        }

        foreach ($table['violations'] ?? [] as $violation) {
            $diagnostics[] = ['type' => 'table_contract_violation'] + $violation;
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function navigation(string $resourceClass): array
    {
        return $this->withoutEmpty([
            'slug' => $this->callStatic($resourceClass, 'getSlug'),
            'label' => $this->normalize($this->callStatic($resourceClass, 'getModelLabel')),
            'plural_label' => $this->normalize($this->callStatic($resourceClass, 'getPluralModelLabel')),
            'group' => $this->normalize($this->callStatic($resourceClass, 'getNavigationGroup')),
            'icon' => $this->normalize($this->callStatic($resourceClass, 'getNavigationIcon')),
        ]);
    }

    private function resolvePolicyClass(string $modelClass, ?string $configuredPolicy): ?string
    {
        if (is_string($configuredPolicy) && class_exists($configuredPolicy)) {
            return $configuredPolicy;
        }

        try {
            $policy = Gate::getPolicyFor($modelClass);

            if (is_object($policy)) {
                return $policy::class;
            }

            if (is_string($policy) && class_exists($policy)) {
                return $policy;
            }
        } catch (Throwable) {
            //
        }

        $conventional = 'App\\Policies\\'.class_basename($modelClass).'Policy';

        return class_exists($conventional) ? $conventional : null;
    }

    /**
     * @return list<string>
     */
    private function policyAbilities(string $policyClass): array
    {
        $abilities = [];

        foreach ((new ReflectionClass($policyClass))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->getDeclaringClass()->getName() !== $policyClass
                || $method->isConstructor()
                || $method->isDestructor()
                || $method->isStatic()
                || Str::startsWith($method->getName(), '__')
                || in_array($method->getName(), ['allow', 'before', 'deny', 'denyAsNotFound', 'denyWithStatus'], true)
            ) {
                continue;
            }

            $abilities[] = $method->getName();
        }

        sort($abilities);

        return array_values(array_unique($abilities));
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return list<string>
     */
    private function abilitiesFromPages(array $pages): array
    {
        $abilities = [];

        foreach ($pages as $page) {
            $abilities = [...$abilities, ...$this->abilitiesFromActions($page['header_actions'] ?? [])];
        }

        return $abilities;
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return list<string>
     */
    private function abilitiesFromActions(array $actions): array
    {
        $abilities = [];

        foreach ($actions as $action) {
            $abilities = [...$abilities, ...($action['abilities'] ?? [])];

            if (is_array($action['actions'] ?? null)) {
                $abilities = [...$abilities, ...$this->abilitiesFromActions($action['actions'])];
            }
        }

        return $abilities;
    }

    /**
     * @return list<array{key: string, message: string}>
     */
    private function sourceTableViolations(?string $definitionClass): array
    {
        if (! is_string($definitionClass) || ! class_exists($definitionClass)) {
            return [];
        }

        $file = (new ReflectionClass($definitionClass))->getFileName();
        $source = is_string($file) ? file_get_contents($file) : false;

        if (! is_string($source)) {
            return [];
        }

        $patterns = [
            'bulk_actions_api' => '/->\s*(?:bulkActions|pushBulkActions|groupedBulkActions|getBulkActions)\s*\(/',
            'bulk_action_class' => '/\b(?:BulkAction|BulkActionGroup|DeleteBulkAction)::make\s*\(/',
            'toolbar_actions_api' => '/->\s*toolbarActions\s*\(/',
        ];
        $violations = [];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $violations[] = [
                    'key' => $key,
                    'message' => 'Forbidden table action API found in '.class_basename($definitionClass).'.',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param  list<array{key: string, message: string}>  $violations
     * @return list<array{key: string, message: string}>
     */
    private function uniqueViolations(array $violations): array
    {
        $unique = [];

        foreach ($violations as $violation) {
            $unique[$violation['key']] = $violation;
        }

        return array_values($unique);
    }

    /**
     * @return array<string, class-string>
     */
    private function pageClasses(string $resourceClass): array
    {
        $pages = [];

        foreach ($resourceClass::getPages() as $key => $registration) {
            if (is_object($registration) && method_exists($registration, 'getPage')) {
                $pageClass = $registration->getPage();

                if (is_string($pageClass) && class_exists($pageClass)) {
                    $pages[(string) $key] = $pageClass;
                }
            }
        }

        return $pages;
    }

    /**
     * @return list<class-string<RelationManager>>
     */
    private function relationManagerClasses(string $resourceClass): array
    {
        $classes = [];
        $collect = function (array $relations) use (&$classes, &$collect): void {
            foreach ($relations as $relation) {
                if ($relation instanceof RelationGroup) {
                    $collect($relation->getManagers());
                } elseif ($relation instanceof RelationManagerConfiguration) {
                    $classes[] = $relation->relationManager;
                } elseif (is_string($relation) && is_subclass_of($relation, RelationManager::class)) {
                    $classes[] = $relation;
                }
            }
        };
        $collect($resourceClass::getRelations());

        sort($classes);

        return array_values(array_unique($classes));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function resourceAliases(array $entry): array
    {
        $linkedClasses = array_values(array_filter([
            $entry['form'],
            $entry['table'],
            ...array_values($entry['pages']),
            ...$entry['relations'],
        ], 'is_string'));
        $aliases = [
            $entry['class'],
            class_basename($entry['class']),
            Str::beforeLast(class_basename($entry['class']), 'Resource'),
            $entry['enum_case'],
            $entry['key'],
            $entry['model'],
            class_basename($entry['model']),
            ...$linkedClasses,
            ...array_map(class_basename(...), $linkedClasses),
        ];

        return array_values(array_unique(array_map(
            static fn (string $alias): string => Str::lower($alias),
            array_filter($aliases, 'is_string'),
        )));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function requiredUniqueAliases(array $entry): array
    {
        return array_map(Str::lower(...), [
            $entry['class'],
            $entry['enum_case'],
            $entry['key'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function resolveResourceClass(string $resource, array $catalog): string
    {
        $lookup = Str::lower(trim($resource));

        if (isset($catalog['lookup'][$lookup])) {
            return $catalog['lookup'][$lookup];
        }

        $keys = array_map(
            static fn (array $entry): string => $entry['key'],
            $catalog['resources'],
        );
        sort($keys);

        throw new RuntimeException(sprintf(
            'Unable to resolve registered resource [%s]. Available keys: %s',
            $resource,
            implode(', ', $keys),
        ));
    }

    private function resourceAttribute(string $resourceClass): ?DiscoverAsResource
    {
        $attributes = (new ReflectionClass($resourceClass))->getAttributes(DiscoverAsResource::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * @return array<string, string|null>|null
     */
    private function attributePayload(?DiscoverAsResource $attribute): ?array
    {
        if ($attribute === null) {
            return null;
        }

        return [
            'key' => $attribute->key,
            'form' => $attribute->form,
            'table' => $attribute->table,
            'policy' => $attribute->policy,
        ];
    }

    private function defaultResourceKey(string $resourceClass): string
    {
        return (string) Str::of(class_basename($resourceClass))
            ->beforeLast('Resource')
            ->snake();
    }

    /**
     * @param  array<string, class-string>  $pages
     */
    private function firstListPageClass(array $pages): ?string
    {
        foreach ($pages as $pageClass) {
            if (is_subclass_of($pageClass, ListRecords::class)) {
                return $pageClass;
            }
        }

        return null;
    }

    /**
     * @param  array<string, class-string>  $pages
     */
    private function firstPageClass(array $pages): ?string
    {
        $pageClass = reset($pages);

        return is_string($pageClass) ? $pageClass : null;
    }

    private function pageKind(string $pageClass): string
    {
        return match (true) {
            is_subclass_of($pageClass, ListRecords::class) => 'list',
            is_subclass_of($pageClass, CreateRecord::class) => 'create',
            is_subclass_of($pageClass, EditRecord::class) => 'edit',
            is_subclass_of($pageClass, ViewRecord::class) => 'view',
            is_subclass_of($pageClass, ManageRelatedRecords::class) => 'manage_related_records',
            default => class_basename($pageClass),
        };
    }

    private function makeModel(string $modelClass): ?Model
    {
        try {
            $model = $this->app->make($modelClass);

            return $model instanceof Model ? $model : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function callStatic(string $class, string $method): mixed
    {
        try {
            if (! class_exists($class) || ! method_exists($class, $method)) {
                return null;
            }

            $reflection = new ReflectionMethod($class, $method);

            if (! $reflection->isPublic() || ! $reflection->isStatic() || $reflection->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            return $class::$method();
        } catch (Throwable) {
            return null;
        }
    }

    private function callPublic(object $object, string $method): mixed
    {
        try {
            if (! method_exists($object, $method)) {
                return null;
            }

            $reflection = new ReflectionMethod($object, $method);

            if (! $reflection->isPublic() || $reflection->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            return $object->{$method}();
        } catch (Throwable) {
            return null;
        }
    }

    private function callAnyVisibility(object $object, string $method): mixed
    {
        if (! method_exists($object, $method)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($object, $method);

            if ($reflection->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            if ($reflection->isPublic()) {
                return $object->{$method}();
            }

            $invoker = Closure::bind(fn (): mixed => $this->{$method}(), $object, $object::class);

            return $invoker();
        } catch (Throwable) {
            return null;
        }
    }

    private function protectedProperty(object $object, string $property): mixed
    {
        try {
            $reflection = new ReflectionClass($object);

            while ($reflection !== false) {
                if ($reflection->hasProperty($property)) {
                    return $reflection->getProperty($property)->getValue($object);
                }

                $reflection = $reflection->getParentClass();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param  object[]  $objects
     * @return array<string, int>
     */
    private function typeCounts(array $objects): array
    {
        $types = [];

        foreach ($objects as $object) {
            $type = class_basename($object);
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        ksort($types);

        return $types;
    }

    /**
     * @return array<string, int|string|null>|null
     */
    private function classSource(string $class): ?array
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();

        if (! is_string($file)) {
            return null;
        }

        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return [
            'file' => Str::startsWith($file, $base) ? Str::after($file, $base) : $file,
            'start_line' => $reflection->getStartLine(),
            'end_line' => $reflection->getEndLine(),
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Htmlable) {
            $value = $value->toHtml();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            return (string) Str::of(strip_tags($value))->squish()->limit(160, '...');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutEmpty(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && $value !== [],
        );
    }
}
