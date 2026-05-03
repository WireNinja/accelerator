<?php

namespace WireNinja\Accelerator\Support\Filament;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Spatie\StructureDiscoverer\Discover;
use Throwable;
use WireNinja\Accelerator\Attributes\DiscoverAsForm;
use WireNinja\Accelerator\Attributes\DiscoverAsPage;
use WireNinja\Accelerator\Attributes\DiscoverAsRelationManager;
use WireNinja\Accelerator\Attributes\DiscoverAsResource;
use WireNinja\Accelerator\Attributes\DiscoverAsTable;
use WireNinja\Accelerator\Attributes\DiscoverAsWidget;
use WireNinja\Accelerator\Attributes\DiscoverShouldMinify;

class ResourceContextScanner
{
    /**
     * @var ?array{
     *     resources: array<string, array<string, mixed>>,
     *     forms: array<string, array<string, mixed>>,
     *     tables: array<string, array<string, mixed>>,
     *     relation_managers: array<string, array<string, mixed>>,
     *     pages: array<string, array<string, mixed>>,
     *     widgets: array<string, array<string, mixed>>,
     *     resource_lookup: array<string, string>,
     *     diagnostics: list<array<string, mixed>>
     * }
     */
    protected ?array $catalog = null;

    public function __construct(
        protected Application $app,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function scan(?string $resource = null, bool $includeRegistry = false, bool $expandMinified = false): array
    {
        $catalog = $this->catalog();
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'resources_discovered' => count($catalog['resources']),
                'forms_discovered' => count($catalog['forms']),
                'tables_discovered' => count($catalog['tables']),
                'widgets_discovered' => count($catalog['widgets']),
                'registry_diagnostics' => count($catalog['diagnostics']),
                'expand_minified' => $expandMinified,
            ],
        ];

        if ($resource === null || $resource === '') {
            $payload['registry'] = $this->describeRegistry($catalog, $expandMinified);

            return $payload;
        }

        $resourceClass = $this->resolveResourceClass($resource, $catalog);
        $resourcePayload = $this->describeResource($resourceClass, $catalog, $expandMinified, false);

        $payload['resource'] = $resourcePayload;
        $payload['summary']['requested_resource'] = $resource;
        $payload['summary']['resolved_resource'] = $resourceClass;
        $payload['summary']['complexity_score'] = $resourcePayload['complexity']['score'];

        if ($includeRegistry) {
            $payload['registry'] = $this->describeRegistry($catalog, $expandMinified);
        }

        return $payload;
    }

    /**
     * @return array{
     *     resources: array<string, array<string, mixed>>,
     *     forms: array<string, array<string, mixed>>,
     *     tables: array<string, array<string, mixed>>,
     *     relation_managers: array<string, array<string, mixed>>,
     *     pages: array<string, array<string, mixed>>,
     *     widgets: array<string, array<string, mixed>>,
     *     resource_lookup: array<string, string>,
     *     diagnostics: list<array<string, mixed>>
     * }
     */
    protected function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $resources = [];
        $forms = [];
        $tables = [];
        $relationManagers = [];
        $pages = [];
        $widgets = [];
        $diagnostics = [];

        foreach ($this->discoverAnnotatedClasses(DiscoverAsResource::class) as $resourceClass) {
            /** @var ?DiscoverAsResource $attribute */
            $attribute = $this->getAttributeInstance($resourceClass, DiscoverAsResource::class);

            $resources[$resourceClass] = [
                'class' => $resourceClass,
                'key' => $attribute?->key ?? $this->defaultResourceKey($resourceClass),
                'form' => $attribute?->form,
                'table' => $attribute?->table,
                'model' => $this->callPublicMethod($resourceClass, 'getModel', true),
                'minified' => $this->hasAttribute($resourceClass, DiscoverShouldMinify::class),
                'minify_reason' => $this->getMinifyReason($resourceClass),
                'source' => $this->describeClassSource($resourceClass),
            ];
        }

        foreach ($this->discoverAnnotatedClasses(DiscoverAsForm::class) as $formClass) {
            /** @var ?DiscoverAsForm $attribute */
            $attribute = $this->getAttributeInstance($formClass, DiscoverAsForm::class);

            $forms[$formClass] = [
                'class' => $formClass,
                'resource' => $attribute?->resource,
                'minified' => $this->hasAttribute($formClass, DiscoverShouldMinify::class),
                'minify_reason' => $this->getMinifyReason($formClass),
                'source' => $this->describeClassSource($formClass),
            ];
        }

        foreach ($this->discoverAnnotatedClasses(DiscoverAsTable::class) as $tableClass) {
            /** @var ?DiscoverAsTable $attribute */
            $attribute = $this->getAttributeInstance($tableClass, DiscoverAsTable::class);

            $tables[$tableClass] = [
                'class' => $tableClass,
                'resource' => $attribute?->resource,
                'minified' => $this->hasAttribute($tableClass, DiscoverShouldMinify::class),
                'minify_reason' => $this->getMinifyReason($tableClass),
                'source' => $this->describeClassSource($tableClass),
            ];
        }

        foreach ($this->discoverAnnotatedClasses(DiscoverAsRelationManager::class) as $relationManagerClass) {
            /** @var ?DiscoverAsRelationManager $attribute */
            $attribute = $this->getAttributeInstance($relationManagerClass, DiscoverAsRelationManager::class);

            $relationManagers[$relationManagerClass] = [
                'class' => $relationManagerClass,
                'resource' => $attribute?->resource,
                'relationship' => $attribute?->relationship,
                'minified' => $this->hasAttribute($relationManagerClass, DiscoverShouldMinify::class),
                'minify_reason' => $this->getMinifyReason($relationManagerClass),
                'source' => $this->describeClassSource($relationManagerClass),
            ];
        }

        foreach ($this->discoverAnnotatedClasses(DiscoverAsPage::class) as $pageClass) {
            /** @var ?DiscoverAsPage $attribute */
            $attribute = $this->getAttributeInstance($pageClass, DiscoverAsPage::class);

            $pages[$pageClass] = [
                'class' => $pageClass,
                'resource' => $attribute?->resource,
                'key' => $attribute?->key,
                'source' => $this->describeClassSource($pageClass),
            ];
        }

        foreach ($this->discoverAnnotatedClasses(DiscoverAsWidget::class) as $widgetClass) {
            /** @var ?DiscoverAsWidget $attribute */
            $attribute = $this->getAttributeInstance($widgetClass, DiscoverAsWidget::class);

            $widgets[$widgetClass] = [
                'class' => $widgetClass,
                'resource' => $attribute?->resource,
                'key' => $attribute?->key,
                'minified' => $this->hasAttribute($widgetClass, DiscoverShouldMinify::class),
                'minify_reason' => $this->getMinifyReason($widgetClass),
                'source' => $this->describeClassSource($widgetClass),
            ];
        }

        $formsByResource = [];

        foreach ($forms as $formClass => $form) {
            if (is_string($form['resource']) && $form['resource'] !== '') {
                $formsByResource[$form['resource']][] = $formClass;
            }
        }

        $tablesByResource = [];

        foreach ($tables as $tableClass => $table) {
            if (is_string($table['resource']) && $table['resource'] !== '') {
                $tablesByResource[$table['resource']][] = $tableClass;
            }
        }

        foreach ($relationManagers as $relationManagerClass => $relationManager) {
            if (! is_string($relationManager['resource']) || $relationManager['resource'] === '') {
                continue;
            }

            if (! isset($resources[$relationManager['resource']])) {
                $diagnostics[] = [
                    'type' => 'relation_manager_resource_not_annotated',
                    'relation_manager' => $relationManagerClass,
                    'resource' => $relationManager['resource'],
                ];
            }
        }

        foreach ($pages as $pageClass => $page) {
            if (! is_string($page['resource']) || $page['resource'] === '') {
                continue;
            }

            if (! isset($resources[$page['resource']])) {
                $diagnostics[] = [
                    'type' => 'page_resource_not_annotated',
                    'page' => $pageClass,
                    'resource' => $page['resource'],
                ];
            }
        }

        foreach ($widgets as $widgetClass => $widget) {
            if (! is_string($widget['resource']) || $widget['resource'] === '') {
                continue;
            }

            if (! isset($resources[$widget['resource']])) {
                $diagnostics[] = [
                    'type' => 'widget_resource_not_annotated',
                    'widget' => $widgetClass,
                    'resource' => $widget['resource'],
                ];
            }
        }

        foreach ($resources as $resourceClass => &$resourceInfo) {
            $discoveredForms = $formsByResource[$resourceClass] ?? [];
            $discoveredTables = $tablesByResource[$resourceClass] ?? [];

            if ($resourceInfo['form'] === null && $discoveredForms !== []) {
                $resourceInfo['form'] = $discoveredForms[0];
            }

            if ($resourceInfo['table'] === null && $discoveredTables !== []) {
                $resourceInfo['table'] = $discoveredTables[0];
            }

            if (count($discoveredForms) > 1) {
                $diagnostics[] = [
                    'type' => 'multiple_forms_for_resource',
                    'resource' => $resourceClass,
                    'forms' => array_values($discoveredForms),
                ];
            }

            if (count($discoveredTables) > 1) {
                $diagnostics[] = [
                    'type' => 'multiple_tables_for_resource',
                    'resource' => $resourceClass,
                    'tables' => array_values($discoveredTables),
                ];
            }

            if (is_string($resourceInfo['form']) && ! isset($forms[$resourceInfo['form']])) {
                $diagnostics[] = [
                    'type' => 'resource_form_not_annotated',
                    'resource' => $resourceClass,
                    'form' => $resourceInfo['form'],
                ];
            }

            if (is_string($resourceInfo['table']) && ! isset($tables[$resourceInfo['table']])) {
                $diagnostics[] = [
                    'type' => 'resource_table_not_annotated',
                    'resource' => $resourceClass,
                    'table' => $resourceInfo['table'],
                ];
            }
        }

        unset($resourceInfo);

        $resourceLookup = [];

        foreach ($resources as $resourceClass => $resourceInfo) {
            foreach ($this->resourceAliases($resourceInfo) as $alias) {
                if (isset($resourceLookup[$alias]) && $resourceLookup[$alias] !== $resourceClass) {
                    $diagnostics[] = [
                        'type' => 'duplicate_resource_alias',
                        'alias' => $alias,
                        'resources' => [$resourceLookup[$alias], $resourceClass],
                    ];

                    continue;
                }

                $resourceLookup[$alias] = $resourceClass;
            }
        }

        foreach ($forms as $formClass => $formInfo) {
            if (! is_string($formInfo['resource']) || $formInfo['resource'] === '') {
                continue;
            }

            foreach ($this->linkedClassAliases($formClass) as $alias) {
                $resourceLookup[$alias] = $formInfo['resource'];
            }
        }

        foreach ($tables as $tableClass => $tableInfo) {
            if (! is_string($tableInfo['resource']) || $tableInfo['resource'] === '') {
                continue;
            }

            foreach ($this->linkedClassAliases($tableClass) as $alias) {
                $resourceLookup[$alias] = $tableInfo['resource'];
            }
        }

        foreach ($relationManagers as $relationManagerInfo) {
            if (! is_string($relationManagerInfo['resource']) || $relationManagerInfo['resource'] === '') {
                continue;
            }

            foreach ($this->relationManagerAliases($relationManagerInfo) as $alias) {
                $resourceLookup[$alias] = $relationManagerInfo['resource'];
            }
        }

        foreach ($pages as $pageInfo) {
            if (! is_string($pageInfo['resource']) || $pageInfo['resource'] === '') {
                continue;
            }

            foreach ($this->pageAliases($pageInfo) as $alias) {
                $resourceLookup[$alias] = $pageInfo['resource'];
            }
        }

        foreach ($widgets as $widgetInfo) {
            if (! is_string($widgetInfo['resource']) || $widgetInfo['resource'] === '') {
                continue;
            }

            foreach ($this->widgetAliases($widgetInfo) as $alias) {
                $resourceLookup[$alias] = $widgetInfo['resource'];
            }
        }

        ksort($resources);
        ksort($forms);
        ksort($tables);
        ksort($relationManagers);
        ksort($pages);
        ksort($widgets);
        ksort($resourceLookup);

        return $this->catalog = [
            'resources' => $resources,
            'forms' => $forms,
            'tables' => $tables,
            'relation_managers' => $relationManagers,
            'pages' => $pages,
            'widgets' => $widgets,
            'resource_lookup' => $resourceLookup,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>|string>>  $catalog
     * @return list<array<string, mixed>>
     */
    protected function describeRegistry(array $catalog, bool $expandMinified): array
    {
        $resources = [];

        foreach (array_keys($catalog['resources']) as $resourceClass) {
            $resource = $this->describeResource($resourceClass, $catalog, $expandMinified, true);
            $resources[] = [
                'key' => $resource['key'],
                'class' => $resource['class'],
                'model' => $resource['model']['class'],
                'model_table' => $resource['model']['table'],
                'policy_class' => $resource['authorization']['policy']['class'] ?? null,
                'action_abilities' => $resource['authorization']['action_abilities'] ?? [],
                'form_class' => $resource['form']['definition_class']['class'] ?? null,
                'table_class' => $resource['table']['definition_class']['class'] ?? null,
                'relation_manager_count' => count($resource['relation_managers']),
                'complexity_score' => $resource['complexity']['score'],
                'minified' => $resource['discovery']['should_minify'],
                'inputs' => $this->resourceAliases($catalog['resources'][$resourceClass]),
                'source' => $resource['source'],
            ];
        }

        usort($resources, fn (array $left, array $right): int => [$right['complexity_score'], $left['key']] <=> [$left['complexity_score'], $right['key']]);

        return [
            'summary' => [
                'resources' => count($catalog['resources']),
                'forms' => count($catalog['forms']),
                'tables' => count($catalog['tables']),
                'relation_managers' => count($catalog['relation_managers']),
                'pages' => count($catalog['pages']),
                'widgets' => count($catalog['widgets']),
            ],
            'resources' => $resources,
            'forms' => array_values(array_map(fn (array $form): array => [
                'class' => $form['class'],
                'resource' => $form['resource'],
                'minified' => $form['minified'],
                'source' => $form['source'],
            ], $catalog['forms'])),
            'tables' => array_values(array_map(fn (array $table): array => [
                'class' => $table['class'],
                'resource' => $table['resource'],
                'minified' => $table['minified'],
                'source' => $table['source'],
            ], $catalog['tables'])),
            'relation_managers' => array_values(array_map(fn (array $relationManager): array => [
                'class' => $relationManager['class'],
                'resource' => $relationManager['resource'],
                'relationship' => $relationManager['relationship'],
                'minified' => $relationManager['minified'],
                'source' => $relationManager['source'],
            ], $catalog['relation_managers'])),
            'pages' => array_values(array_map(fn (array $page): array => [
                'class' => $page['class'],
                'resource' => $page['resource'],
                'key' => $page['key'],
                'source' => $page['source'],
            ], $catalog['pages'])),
            'widgets' => array_values(array_map(fn (array $widget): array => [
                'class' => $widget['class'],
                'resource' => $widget['resource'],
                'key' => $widget['key'],
                'minified' => $widget['minified'],
                'source' => $widget['source'],
            ], $catalog['widgets'])),
            'diagnostics' => $catalog['diagnostics'],
        ];
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>|string>>  $catalog
     * @return array<string, mixed>
     */
    protected function describeResource(string $resourceClass, array $catalog, bool $expandMinified, bool $summaryOnly): array
    {
        $catalogEntry = $catalog['resources'][$resourceClass] ?? [
            'class' => $resourceClass,
            'key' => $this->defaultResourceKey($resourceClass),
            'form' => null,
            'table' => null,
            'model' => $this->callPublicMethod($resourceClass, 'getModel', true),
            'minified' => $this->hasAttribute($resourceClass, DiscoverShouldMinify::class),
            'minify_reason' => $this->getMinifyReason($resourceClass),
            'source' => $this->describeClassSource($resourceClass),
        ];

        $resourceMinified = (bool) $catalogEntry['minified'] && (! $expandMinified);
        $modelClass = is_string($catalogEntry['model']) ? $catalogEntry['model'] : null;
        $model = $modelClass ? $this->makeModel($modelClass) : null;
        $formClass = is_string($catalogEntry['form']) ? $catalogEntry['form'] : null;
        $tableClass = is_string($catalogEntry['table']) ? $catalogEntry['table'] : null;
        $policyPayload = $this->describeResourcePolicy($modelClass);

        $pages = $this->describePages($resourceClass, $catalog, $policyPayload['class'] ?? null);
        $widgets = $this->describeWidgets($resourceClass, $pages, $catalog);
        $relationPageClass = $this->resolveRelationPageClass($pages);
        $resourceHookSources = [
            'form' => $this->describeMethodSource($resourceClass, 'form'),
            'table' => $this->describeMethodSource($resourceClass, 'table'),
            'get_relations' => $this->describeMethodSource($resourceClass, 'getRelations'),
            'get_pages' => $this->describeMethodSource($resourceClass, 'getPages'),
        ];

        $formSchema = $this->buildResourceFormSchema($resourceClass);
        $table = $this->buildResourceTable($resourceClass, $modelClass);

        $formMinified = (! $expandMinified) && ($resourceMinified || $this->shouldClassBeMinified($formClass));
        $tableMinified = (! $expandMinified) && ($resourceMinified || $this->shouldClassBeMinified($tableClass));

        $relationManagers = $this->describeRelationEntries(
            $this->normalizeRelationEntries($this->callPublicMethod($resourceClass, 'getRelations', true) ?? []),
            $modelClass,
            $relationPageClass,
            $resourceMinified,
            $expandMinified,
            $summaryOnly,
        );

        $formPayload = $this->describeSchemaPayload(
            $formSchema,
            $formClass,
            $formMinified || $summaryOnly,
            'configure',
            $resourceHookSources['form'],
        );

        $tablePayload = $this->describeTablePayload(
            $table,
            $tableClass,
            $tableMinified || $summaryOnly,
            'configure',
            $resourceHookSources['table'],
            $policyPayload['class'] ?? null,
        );

        return [
            'class' => $resourceClass,
            'key' => $catalogEntry['key'],
            'source' => $catalogEntry['source'],
            'discovery' => $this->describeDiscoveryMetadata($resourceClass),
            'model' => [
                'class' => $modelClass,
                'table' => $model?->getTable(),
                'source' => $modelClass ? $this->describeClassSource($modelClass) : null,
            ],
            'navigation' => [
                'slug' => $this->callPublicMethod($resourceClass, 'getSlug', true),
                'model_label' => $this->normalizeValue($this->callPublicMethod($resourceClass, 'getModelLabel', true)),
                'plural_model_label' => $this->normalizeValue($this->callPublicMethod($resourceClass, 'getPluralModelLabel', true)),
                'navigation_label' => $this->normalizeValue($this->callPublicMethod($resourceClass, 'getNavigationLabel', true)),
                'navigation_group' => $this->normalizeValue($this->callPublicMethod($resourceClass, 'getNavigationGroup', true)),
                'navigation_icon' => $this->normalizeValue($this->callPublicMethod($resourceClass, 'getNavigationIcon', true)),
                'active_navigation_icon' => $this->normalizeValue($this->callPublicMethod($resourceClass, 'getActiveNavigationIcon', true)),
                'record_title_attribute' => $this->callPublicMethod($resourceClass, 'getRecordTitleAttribute', true),
                'cluster' => $this->callPublicMethod($resourceClass, 'getCluster', true),
            ],
            'authorization' => $this->filterNullValues([
                'policy' => $policyPayload,
                'action_abilities' => $this->collectResourceActionAbilities($pages, $tablePayload),
            ]),
            'pages' => $pages,
            'widgets' => $widgets,
            'hooks' => $resourceHookSources,
            'form' => $formPayload,
            'table' => $tablePayload,
            'relation_managers' => $relationManagers,
            'complexity' => $this->scoreComplexity($formPayload, $tablePayload, $relationManagers),
            'diagnostics' => $this->buildResourceDiagnostics($resourceClass, $catalogEntry, $catalog),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function describeRelationEntries(array $entries, ?string $ownerModelClass, ?string $pageClass, bool $resourceMinified, bool $expandMinified, bool $summaryOnly): array
    {
        $payload = [];

        foreach ($entries as $entry) {
            if ($entry['kind'] === 'group') {
                $payload[] = [
                    'kind' => 'group',
                    'label' => $entry['label'],
                    'managers' => $this->describeRelationEntries($entry['managers'], $ownerModelClass, $pageClass, $resourceMinified, $expandMinified, $summaryOnly),
                ];

                continue;
            }

            if ($entry['kind'] !== 'manager') {
                $payload[] = $entry;

                continue;
            }

            $managerClass = $entry['manager_class'];
            $shouldMinify = (! $expandMinified) && ($resourceMinified || $this->shouldClassBeMinified($managerClass));
            $payload[] = $this->describeRelationManager($managerClass, $entry['properties'], $ownerModelClass, $pageClass, $shouldMinify || $summaryOnly);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function describeRelationManager(string $managerClass, array $properties, ?string $ownerModelClass, ?string $pageClass, bool $shouldMinify): array
    {
        $payload = [
            'kind' => 'manager',
            'class' => $managerClass,
            'source' => $this->describeClassSource($managerClass),
            'discovery' => $this->describeDiscoveryMetadata($managerClass),
            'configuration_properties' => $properties,
            'relationship' => $this->callPublicMethod($managerClass, 'getRelationshipName', true),
            'relationship_title' => $this->normalizeValue($this->callPublicMethod($managerClass, 'getRelationshipTitle', true)),
            'related_resource' => $this->callPublicMethod($managerClass, 'getRelatedResource', true),
        ];

        try {
            /** @var RelationManager $instance */
            $instance = $this->app->make($managerClass);

            if ($ownerModelClass !== null) {
                $instance->ownerRecord = $this->makeModel($ownerModelClass) ?? new $ownerModelClass;
            }

            $instance->pageClass = $pageClass;

            $payload['form'] = $this->describeSchemaPayload(
                $instance->form(Schema::make($instance)),
                $managerClass,
                $shouldMinify,
                'form',
                $this->describeMethodSource($managerClass, 'form'),
            );
            $payload['table'] = $this->describeTablePayload(
                $instance->table(Table::make($instance)),
                $managerClass,
                $shouldMinify,
                'table',
                $this->describeMethodSource($managerClass, 'table'),
            );
        } catch (Throwable $throwable) {
            $payload['error'] = $throwable->getMessage();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeSchemaPayload(Schema $schema, ?string $definitionClass, bool $shouldMinify, string $definitionMethod, ?array $resourceHook): array
    {
        $topLevelComponents = $schema->getComponents(true);
        $flatComponents = [];
        $namedComponents = [];
        $relationshipComponents = [];
        $warnings = [];

        try {
            $flatComponents = $schema->getFlatComponents(true);
        } catch (Throwable $throwable) {
            $flatComponents = $topLevelComponents;
            $warnings[] = [
                'type' => 'schema_introspection_incomplete',
                'message' => 'Schema flattening failed, so the summary falls back to top-level components only.',
                'error' => $this->normalizeValue($throwable->getMessage()),
            ];
        }

        foreach ($flatComponents as $component) {
            $name = $this->normalizeValue($this->callPublicMethod($component, 'getName'));

            if (is_string($name) && $name !== '') {
                $namedComponents[] = $name;
            }

            $relationshipName = $this->normalizeValue($this->callPublicMethod($component, 'getRelationshipName'));

            if (is_string($relationshipName) && $relationshipName !== '') {
                $relationshipComponents[] = [
                    'component' => is_string($name) && $name !== '' ? $name : class_basename($component),
                    'relationship' => $relationshipName,
                ];
            }
        }

        $tree = null;

        if (! $shouldMinify) {
            try {
                $tree = array_values(array_map($this->summarizeSchemaComponent(...), $topLevelComponents));
            } catch (Throwable $throwable) {
                $warnings[] = [
                    'type' => 'schema_tree_incomplete',
                    'message' => 'Schema tree summarization failed, so the tree payload was omitted.',
                    'error' => $this->normalizeValue($throwable->getMessage()),
                ];
            }
        }

        return [
            'definition_class' => $this->describeLinkedClass($definitionClass, $definitionMethod),
            'resource_hook' => $resourceHook,
            'minified' => $shouldMinify,
            'summary' => [
                'top_level_components' => count($topLevelComponents),
                'total_components' => count($flatComponents),
                'component_types' => $this->countTypes($flatComponents),
                'named_components' => array_values(array_unique($namedComponents)),
                'relationship_components' => $relationshipComponents,
            ],
            'warnings' => $warnings,
            'tree' => $tree,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeTablePayload(Table $table, ?string $definitionClass, bool $shouldMinify, string $definitionMethod, ?array $resourceHook, ?string $policyClass = null): array
    {
        $columns = array_values($table->getColumns());
        $filters = array_values($table->getFilters());
        $headerActions = array_values($table->getHeaderActions());
        $recordActions = array_values($table->getRecordActions());
        $bulkActions = array_values($table->getToolbarActions());
        $emptyStateActions = array_values($this->callPublicMethod($table, 'getEmptyStateActions') ?? []);
        $violations = $bulkActions === []
            ? []
            : [[
                'type' => 'bulk_actions_forbidden',
                'message' => 'Bulk actions are forbidden by project rules and should be removed from the table definition.',
                'count' => count($bulkActions),
                'classes' => array_values(array_map(static fn (Action|ActionGroup $action): string => $action::class, $bulkActions)),
            ]];

        return [
            'definition_class' => $this->describeLinkedClass($definitionClass, $definitionMethod),
            'resource_hook' => $resourceHook,
            'minified' => $shouldMinify,
            'summary' => [
                'columns' => count($columns),
                'filters' => count($filters),
                'header_actions' => count($headerActions),
                'record_actions' => count($recordActions),
                'violations' => count($violations),
                'column_types' => $this->countTypes($columns),
                'filter_types' => $this->countTypes($filters),
            ],
            'empty_state' => [
                'icon' => $this->normalizeValue($this->callPublicMethod($table, 'getEmptyStateIcon')),
                'heading' => $this->normalizeValue($this->callPublicMethod($table, 'getEmptyStateHeading')),
                'description' => $this->normalizeValue($this->callPublicMethod($table, 'getEmptyStateDescription')),
                'actions' => array_values(array_map(fn (object $action): array => $this->summarizeAction($action, $policyClass), $emptyStateActions)),
            ],
            'columns' => array_values(array_map($this->summarizeTableColumn(...), $columns)),
            'filters' => array_values(array_map($this->summarizeTableFilter(...), $filters)),
            'header_actions' => array_values(array_map(fn (object $action): array => $this->summarizeAction($action, $policyClass), $headerActions)),
            'record_actions' => array_values(array_map(fn (object $action): array => $this->summarizeAction($action, $policyClass), $recordActions)),
            'violations' => $violations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizeSchemaComponent(object $component): array
    {
        $summary = $this->filterNullValues([
            'class' => $component::class,
            'type' => class_basename($component),
            'name' => $this->normalizeValue($this->callPublicMethod($component, 'getName')),
            'label' => $this->normalizeValue($this->callPublicMethod($component, 'getLabel')),
            'state_path' => $this->normalizeValue($this->callPublicMethod($component, 'getStatePath')),
            'relationship' => $this->normalizeValue($this->callPublicMethod($component, 'getRelationshipName')),
            'icon' => $this->normalizeValue($this->callPublicMethod($component, 'getIcon')),
            'required' => $this->callPublicMethod($component, 'isRequired'),
            'searchable' => $this->callPublicMethod($component, 'isSearchable'),
            'multiple' => $this->callPublicMethod($component, 'isMultiple'),
            'disabled' => $this->callPublicMethod($component, 'isDisabled'),
            'hidden' => $this->callPublicMethod($component, 'isHidden'),
            'column_span' => $this->normalizeValue($this->callPublicMethod($component, 'getColumnSpan')),
            'column_start' => $this->normalizeValue($this->callPublicMethod($component, 'getColumnStart')),
        ]);

        $childSchemas = $this->callPublicMethod($component, 'getChildSchemas');

        if (is_array($childSchemas) && $childSchemas !== []) {
            $summary['children'] = [];

            foreach ($childSchemas as $childSchema) {
                if (! $childSchema instanceof Schema) {
                    continue;
                }

                foreach ($childSchema->getComponents(true) as $childComponent) {
                    $summary['children'][] = $this->summarizeSchemaComponent($childComponent);
                }
            }
        }

        $headerActions = $this->callPublicMethod($component, 'getHeaderActions');

        if (is_array($headerActions) && $headerActions !== []) {
            $summary['header_actions'] = array_values(array_map($this->summarizeAction(...), $headerActions));
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizeTableColumn(object $column): array
    {
        return $this->filterNullValues([
            'class' => $column::class,
            'type' => class_basename($column),
            'name' => $this->normalizeValue($this->callPublicMethod($column, 'getName')),
            'label' => $this->normalizeValue($this->callPublicMethod($column, 'getLabel')),
            'searchable' => $this->callPublicMethod($column, 'isSearchable'),
            'sortable' => $this->callPublicMethod($column, 'isSortable'),
            'toggleable' => $this->callPublicMethod($column, 'isToggleable'),
            'hidden_by_default' => $this->callPublicMethod($column, 'isToggledHiddenByDefault'),
            'description' => $this->normalizeValue($this->callPublicMethod($column, 'getDescription')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizeTableFilter(object $filter): array
    {
        return $this->filterNullValues([
            'class' => $filter::class,
            'type' => class_basename($filter),
            'name' => $this->normalizeValue($this->callPublicMethod($filter, 'getName')),
            'label' => $this->normalizeValue($this->callPublicMethod($filter, 'getLabel')),
            'attribute' => $this->normalizeValue($this->callPublicMethod($filter, 'getAttribute')),
            'relationship' => $this->normalizeValue($this->callPublicMethod($filter, 'getRelationshipName')),
            'multiple' => $this->callPublicMethod($filter, 'isMultiple'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizeAction(object $action, ?string $policyClass = null): array
    {
        if ($action instanceof ActionGroup) {
            return $this->filterNullValues([
                'class' => $action::class,
                'type' => class_basename($action),
                'label' => $this->normalizeValue($this->callPublicMethod($action, 'getLabel')),
                'icon' => $this->normalizeValue($this->callPublicMethod($action, 'getIcon')),
                'authorization' => $this->summarizeActionAuthorization($action, $policyClass),
                'actions' => array_values(array_map(fn (object $childAction): array => $this->summarizeAction($childAction, $policyClass), $action->getActions())),
            ]);
        }

        return $this->filterNullValues([
            'class' => $action::class,
            'type' => class_basename($action),
            'name' => $this->normalizeValue($this->callPublicMethod($action, 'getName')),
            'label' => $this->normalizeValue($this->callPublicMethod($action, 'getLabel')),
            'icon' => $this->normalizeValue($this->callPublicMethod($action, 'getIcon')),
            'color' => $this->normalizeValue($this->callPublicMethod($action, 'getColor')),
            'authorization' => $this->summarizeActionAuthorization($action, $policyClass),
        ]);
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $table
     * @param  array<int, array<string, mixed>>  $relationManagers
     * @return array<string, mixed>
     */
    protected function scoreComplexity(array $form, array $table, array $relationManagers): array
    {
        $relationManagerScore = 0;

        foreach ($relationManagers as $relationManager) {
            if (($relationManager['kind'] ?? null) === 'group') {
                foreach ($relationManager['managers'] as $groupedManager) {
                    $relationManagerScore += $this->relationManagerScore($groupedManager);
                }

                continue;
            }

            $relationManagerScore += $this->relationManagerScore($relationManager);
        }

        $score = ($form['summary']['total_components'] ?? 0)
            + (($table['summary']['columns'] ?? 0) * 2)
            + (($table['summary']['filters'] ?? 0) * 3)
            + (($table['summary']['record_actions'] ?? 0) * 2)
            + (($table['summary']['header_actions'] ?? 0) * 2)
            + $relationManagerScore;

        return [
            'score' => $score,
            'signals' => [
                'form_components' => $form['summary']['total_components'] ?? 0,
                'table_columns' => $table['summary']['columns'] ?? 0,
                'table_filters' => $table['summary']['filters'] ?? 0,
                'table_record_actions' => $table['summary']['record_actions'] ?? 0,
                'relation_managers' => count($relationManagers),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $relationManager
     */
    protected function relationManagerScore(array $relationManager): int
    {
        return ($relationManager['form']['summary']['total_components'] ?? 0)
            + (($relationManager['table']['summary']['columns'] ?? 0) * 2)
            + (($relationManager['table']['summary']['filters'] ?? 0) * 3)
            + 6;
    }

    /**
     * @param  array<string, mixed>  $catalogEntry
     * @param  array<string, mixed>  $catalog
     * @return list<array<string, mixed>>
     */
    protected function buildResourceDiagnostics(string $resourceClass, array $catalogEntry, array $catalog): array
    {
        $diagnostics = [];

        if ($catalogEntry['form'] === null) {
            $diagnostics[] = [
                'type' => 'missing_form_link',
                'resource' => $resourceClass,
            ];
        }

        if ($catalogEntry['table'] === null) {
            $diagnostics[] = [
                'type' => 'missing_table_link',
                'resource' => $resourceClass,
            ];
        }

        if (is_string($catalogEntry['form']) && isset($catalog['forms'][$catalogEntry['form']])) {
            $linkedResource = $catalog['forms'][$catalogEntry['form']]['resource'];

            if ($linkedResource !== null && $linkedResource !== $resourceClass) {
                $diagnostics[] = [
                    'type' => 'form_resource_mismatch',
                    'resource' => $resourceClass,
                    'form' => $catalogEntry['form'],
                    'linked_resource' => $linkedResource,
                ];
            }
        }

        if (is_string($catalogEntry['table']) && isset($catalog['tables'][$catalogEntry['table']])) {
            $linkedResource = $catalog['tables'][$catalogEntry['table']]['resource'];

            if ($linkedResource !== null && $linkedResource !== $resourceClass) {
                $diagnostics[] = [
                    'type' => 'table_resource_mismatch',
                    'resource' => $resourceClass,
                    'table' => $catalogEntry['table'],
                    'linked_resource' => $linkedResource,
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>|string>>  $catalog
     */
    protected function resolveResourceClass(string $resource, array $catalog): string
    {
        $trimmed = trim($resource);

        if ($trimmed === '') {
            throw new RuntimeException('Resource identifier can not be empty.');
        }

        if (class_exists($trimmed) && is_subclass_of($trimmed, Resource::class)) {
            return $trimmed;
        }

        $lookupKey = Str::lower($trimmed);

        if (isset($catalog['resource_lookup'][$lookupKey])) {
            return $catalog['resource_lookup'][$lookupKey];
        }

        throw new RuntimeException(sprintf(
            'Unable to resolve resource [%s]. Available keys: %s',
            $resource,
            implode(', ', array_values(array_unique(array_map(fn (array $entry): string => $entry['key'], $catalog['resources']))))
        ));
    }

    /**
     * @param  array<string, mixed>  $resourceInfo
     * @return list<string>
     */
    protected function resourceAliases(array $resourceInfo): array
    {
        $aliases = [
            Str::lower($resourceInfo['class']),
            Str::lower(class_basename($resourceInfo['class'])),
            Str::lower((string) Str::of(class_basename($resourceInfo['class']))->beforeLast('Resource')),
            Str::lower((string) $resourceInfo['key']),
        ];

        if (is_string($resourceInfo['model']) && $resourceInfo['model'] !== '') {
            $aliases[] = Str::lower($resourceInfo['model']);
            $aliases[] = Str::lower(class_basename($resourceInfo['model']));
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    /**
     * @return list<string>
     */
    protected function linkedClassAliases(string $class): array
    {
        return array_values(array_unique(array_filter([
            Str::lower($class),
            Str::lower(class_basename($class)),
            Str::lower((string) Str::of(class_basename($class))->beforeLast('Page')->beforeLast('RelationManager')->snake()),
        ])));
    }

    /**
     * @param  array<string, mixed>  $relationManagerInfo
     * @return list<string>
     */
    protected function relationManagerAliases(array $relationManagerInfo): array
    {
        $aliases = $this->linkedClassAliases($relationManagerInfo['class']);

        if (is_string($relationManagerInfo['resource']) && $relationManagerInfo['resource'] !== '') {
            $resourceKey = $this->defaultResourceKey($relationManagerInfo['resource']);
            $aliases[] = Str::lower($resourceKey.'.'.class_basename($relationManagerInfo['class']));

            if (is_string($relationManagerInfo['relationship']) && $relationManagerInfo['relationship'] !== '') {
                $aliases[] = Str::lower($resourceKey.'.'.$relationManagerInfo['relationship']);
            }
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    /**
     * @param  array<string, mixed>  $pageInfo
     * @return list<string>
     */
    protected function pageAliases(array $pageInfo): array
    {
        $aliases = $this->linkedClassAliases($pageInfo['class']);

        if (is_string($pageInfo['resource']) && $pageInfo['resource'] !== '' && is_string($pageInfo['key']) && $pageInfo['key'] !== '') {
            $aliases[] = Str::lower($this->defaultResourceKey($pageInfo['resource']).'.'.$pageInfo['key']);
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    /**
     * @param  array<string, mixed>  $widgetInfo
     * @return list<string>
     */
    protected function widgetAliases(array $widgetInfo): array
    {
        $aliases = $this->linkedClassAliases($widgetInfo['class']);

        if (is_string($widgetInfo['resource']) && $widgetInfo['resource'] !== '' && is_string($widgetInfo['key']) && $widgetInfo['key'] !== '') {
            $aliases[] = Str::lower($this->defaultResourceKey($widgetInfo['resource']).'.'.$widgetInfo['key']);
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    protected function defaultResourceKey(string $resourceClass): string
    {
        return (string) Str::of(class_basename($resourceClass))
            ->beforeLast('Resource')
            ->snake();
    }

    protected function buildResourceFormSchema(string $resourceClass): Schema
    {
        return $resourceClass::form(Schema::make(new ResourceContextSchemaHost));
    }

    protected function buildResourceTable(string $resourceClass, ?string $modelClass): Table
    {
        $resolvedModelClass = is_string($modelClass) && $modelClass !== '' ? $modelClass : Model::class;
        $host = new ResourceContextTableHost($resolvedModelClass);
        $table = $resourceClass::table(Table::make($host));
        $host->setTable($table);

        return $table;
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>|string>>  $catalog
     * @return list<array<string, mixed>>
     */
    protected function describePages(string $resourceClass, array $catalog, ?string $policyClass = null): array
    {
        $pages = [];

        foreach ($this->callPublicMethod($resourceClass, 'getPages', true) ?? [] as $key => $registration) {
            if (! $registration instanceof PageRegistration) {
                $pages[] = [
                    'key' => $key,
                    'registration_type' => get_debug_type($registration),
                ];

                continue;
            }

            $pageClass = $registration->getPage();

            $page = [
                'key' => $key,
                'class' => $pageClass,
                'kind' => $this->detectPageKind($pageClass),
                'source' => $this->describeClassSource($pageClass),
                'discovery' => $this->describeDiscoveryMetadata($pageClass),
            ];

            try {
                $instance = $this->app->make($pageClass);

                $headerActions = $this->callMethodAnyVisibility($instance, 'getHeaderActions');
                $headerWidgets = $this->callMethodAnyVisibility($instance, 'getHeaderWidgets');
                $footerWidgets = $this->callMethodAnyVisibility($instance, 'getFooterWidgets');
                $tabs = $this->callPublicMethod($instance, 'getTabs');

                $page['header_actions'] = is_array($headerActions)
                    ? array_values(array_map(fn (object $action): array => $this->summarizeAction($action, $policyClass), $headerActions))
                    : [];
                $page['header_widgets'] = is_array($headerWidgets)
                    ? array_values(array_map(fn (mixed $widget): array => $this->describePageWidget($widget, $resourceClass, $pageClass, 'header', $catalog), $headerWidgets))
                    : [];
                $page['footer_widgets'] = is_array($footerWidgets)
                    ? array_values(array_map(fn (mixed $widget): array => $this->describePageWidget($widget, $resourceClass, $pageClass, 'footer', $catalog), $footerWidgets))
                    : [];
                $page['tabs'] = is_array($tabs)
                    ? array_values(array_map(fn (string $tabKey, mixed $tab): array => $this->summarizePageTab($tabKey, $tab), array_keys($tabs), array_values($tabs)))
                    : [];
            } catch (Throwable $throwable) {
                $page['introspection_error'] = $throwable->getMessage();
            }

            if (isset($catalog['pages'][$pageClass]['key']) && is_string($catalog['pages'][$pageClass]['key'])) {
                $page['annotated_key'] = $catalog['pages'][$pageClass]['key'];
            }

            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    protected function resolveRelationPageClass(array $pages): ?string
    {
        foreach (['edit', 'view', 'index', 'create'] as $preferredKey) {
            foreach ($pages as $page) {
                if (($page['key'] ?? null) === $preferredKey && is_string($page['class'] ?? null)) {
                    return $page['class'];
                }
            }
        }

        foreach ($pages as $page) {
            if (is_string($page['class'] ?? null)) {
                return $page['class'];
            }
        }

        return null;
    }

    protected function detectPageKind(string $pageClass): string
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

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeRelationEntries(array $relations): array
    {
        $entries = [];

        foreach ($relations as $relation) {
            if ($relation instanceof RelationGroup) {
                $entries[] = [
                    'kind' => 'group',
                    'label' => $this->normalizeValue($this->callPublicMethod($relation, 'getLabel')),
                    'managers' => $this->normalizeRelationEntries($relation->getManagers()),
                ];

                continue;
            }

            $properties = [];
            $managerClass = null;

            if ($relation instanceof RelationManagerConfiguration) {
                $properties = $relation->getProperties();
                $managerClass = $relation->relationManager;
            }

            if (is_string($relation)) {
                $managerClass = $relation;
            }

            if (is_string($managerClass) && is_subclass_of($managerClass, RelationManager::class)) {
                $entries[] = [
                    'kind' => 'manager',
                    'manager_class' => $managerClass,
                    'properties' => $properties,
                ];

                continue;
            }

            $entries[] = [
                'kind' => 'unknown',
                'type' => get_debug_type($relation),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeLinkedClass(?string $class, string $method): ?array
    {
        if ($class === null || $class === '') {
            return null;
        }

        $data = [
            'class' => $class,
            'exists' => class_exists($class),
        ];

        if (! $data['exists']) {
            return $data;
        }

        $data['source'] = $this->describeClassSource($class);
        $data['method_source'] = $this->describeMethodSource($class, $method);
        $data['discovery'] = $this->describeDiscoveryMetadata($class);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeDiscoveryMetadata(string $class): array
    {
        /** @var ?DiscoverAsResource $resourceAttribute */
        $resourceAttribute = $this->getAttributeInstance($class, DiscoverAsResource::class);
        /** @var ?DiscoverAsForm $formAttribute */
        $formAttribute = $this->getAttributeInstance($class, DiscoverAsForm::class);
        /** @var ?DiscoverAsPage $pageAttribute */
        $pageAttribute = $this->getAttributeInstance($class, DiscoverAsPage::class);
        /** @var ?DiscoverAsRelationManager $relationManagerAttribute */
        $relationManagerAttribute = $this->getAttributeInstance($class, DiscoverAsRelationManager::class);
        /** @var ?DiscoverAsTable $tableAttribute */
        $tableAttribute = $this->getAttributeInstance($class, DiscoverAsTable::class);
        /** @var ?DiscoverAsWidget $widgetAttribute */
        $widgetAttribute = $this->getAttributeInstance($class, DiscoverAsWidget::class);

        return $this->filterNullValues([
            'attributes' => $this->getAttributeClassNames($class),
            'annotated_as_resource' => $resourceAttribute ? [
                'key' => $resourceAttribute->key,
                'form' => $resourceAttribute->form,
                'table' => $resourceAttribute->table,
            ] : null,
            'annotated_as_form' => $formAttribute ? [
                'resource' => $formAttribute->resource,
            ] : null,
            'annotated_as_page' => $pageAttribute ? [
                'resource' => $pageAttribute->resource,
                'key' => $pageAttribute->key,
            ] : null,
            'annotated_as_relation_manager' => $relationManagerAttribute ? [
                'resource' => $relationManagerAttribute->resource,
                'relationship' => $relationManagerAttribute->relationship,
            ] : null,
            'annotated_as_table' => $tableAttribute ? [
                'resource' => $tableAttribute->resource,
            ] : null,
            'annotated_as_widget' => $widgetAttribute ? [
                'resource' => $widgetAttribute->resource,
                'key' => $widgetAttribute->key,
            ] : null,
            'should_minify' => $this->hasAttribute($class, DiscoverShouldMinify::class),
            'minify_reason' => $this->getMinifyReason($class),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizePageTab(string $key, mixed $tab): array
    {
        return $this->filterNullValues([
            'key' => $key,
            'type' => is_object($tab) ? class_basename($tab) : get_debug_type($tab),
            'label' => is_object($tab) ? $this->normalizeValue($this->callPublicMethod($tab, 'getLabel')) : $this->normalizeValue($tab),
            'icon' => is_object($tab) ? $this->normalizeValue($this->callPublicMethod($tab, 'getIcon')) : null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, array<int|string, array<string, mixed>|string>>  $catalog
     * @return list<array<string, mixed>>
     */
    protected function describeWidgets(string $resourceClass, array $pages, array $catalog): array
    {
        $widgets = [];

        foreach ($pages as $page) {
            foreach (['header_widgets', 'footer_widgets'] as $placementKey) {
                foreach ($page[$placementKey] ?? [] as $widget) {
                    if (! is_array($widget) || ! is_string($widget['class'] ?? null)) {
                        continue;
                    }

                    $widgets[$widget['class']] = isset($widgets[$widget['class']])
                        ? $this->mergeWidgetPayload($widgets[$widget['class']], $widget)
                        : $widget;
                }
            }
        }

        foreach ($catalog['widgets'] ?? [] as $widgetClass => $widgetInfo) {
            if (($widgetInfo['resource'] ?? null) !== $resourceClass) {
                continue;
            }

            $described = $this->describeWidgetClass($widgetClass, $resourceClass, $catalog);
            $widgets[$widgetClass] = isset($widgets[$widgetClass])
                ? $this->mergeWidgetPayload($widgets[$widgetClass], $described)
                : $described;
        }

        ksort($widgets);

        return array_values($widgets);
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>|string>>  $catalog
     * @return array<string, mixed>
     */
    protected function describePageWidget(mixed $widget, string $resourceClass, string $pageClass, string $placement, array $catalog): array
    {
        $normalized = $this->normalizeWidgetReference($widget);

        if ($normalized === null) {
            return [
                'type' => get_debug_type($widget),
                'placement' => $placement,
            ];
        }

        $described = $this->describeWidgetClass($normalized['class'], $resourceClass, $catalog, $normalized['properties']);
        $described['placement'] = $placement;
        $described['page_class'] = $pageClass;

        return $described;
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>|string>>  $catalog
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function describeWidgetClass(string $widgetClass, string $resourceClass, array $catalog, array $properties = []): array
    {
        $payload = [
            'class' => $widgetClass,
            'type' => class_basename($widgetClass),
            'kind' => $this->detectWidgetKind($widgetClass),
            'source' => $this->describeClassSource($widgetClass),
            'discovery' => $this->describeDiscoveryMetadata($widgetClass),
            'resource' => $resourceClass,
            'properties' => $properties,
        ];

        if (isset($catalog['widgets'][$widgetClass]['key']) && is_string($catalog['widgets'][$widgetClass]['key'])) {
            $payload['key'] = $catalog['widgets'][$widgetClass]['key'];
        }

        if (! class_exists($widgetClass) || ! is_subclass_of($widgetClass, Widget::class)) {
            return $payload;
        }

        $payload['can_view'] = $this->callPublicMethod($widgetClass, 'canView', true);
        $payload['sort'] = $this->callPublicMethod($widgetClass, 'getSort', true);
        $payload['default_properties'] = $this->normalizeValue($this->callPublicMethod($widgetClass, 'getDefaultProperties', true));

        try {
            /** @var Widget $instance */
            $instance = $this->app->make($widgetClass);

            $payload['column_span'] = $this->normalizeValue($this->callPublicMethod($instance, 'getColumnSpan'));
            $payload['column_start'] = $this->normalizeValue($this->callPublicMethod($instance, 'getColumnStart'));

            if (is_subclass_of($widgetClass, StatsOverviewWidget::class)) {
                $payload['heading'] = $this->normalizeValue($this->callMethodAnyVisibility($instance, 'getHeading'));
                $payload['description'] = $this->normalizeValue($this->callMethodAnyVisibility($instance, 'getDescription'));

                $stats = $this->callMethodAnyVisibility($instance, 'getStats');

                $payload['stats'] = is_array($stats)
                    ? array_values(array_map(fn (mixed $stat): array => $stat instanceof Stat ? $this->summarizeWidgetStat($stat) : ['type' => get_debug_type($stat)], $stats))
                    : [];
            }
        } catch (Throwable $throwable) {
            $payload['introspection_error'] = $throwable->getMessage();
        }

        return $this->filterNullValues($payload);
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $secondary
     * @return array<string, mixed>
     */
    protected function mergeWidgetPayload(array $primary, array $secondary): array
    {
        $placements = array_merge($primary['placements'] ?? [], isset($primary['placement']) ? [$primary['placement']] : []);
        $placements = array_merge($placements, $secondary['placements'] ?? [], isset($secondary['placement']) ? [$secondary['placement']] : []);
        $pages = array_merge($primary['pages'] ?? [], isset($primary['page_class']) ? [$primary['page_class']] : []);
        $pages = array_merge($pages, $secondary['pages'] ?? [], isset($secondary['page_class']) ? [$secondary['page_class']] : []);

        $merged = array_merge($secondary, $primary);
        $merged['placements'] = array_values(array_unique(array_filter($placements)));
        $merged['pages'] = array_values(array_unique(array_filter($pages)));

        unset($merged['placement'], $merged['page_class']);

        return $merged;
    }

    /**
     * @return array{class: string, properties: array<string, mixed>}|null
     */
    protected function normalizeWidgetReference(mixed $widget): ?array
    {
        if (is_string($widget)) {
            return [
                'class' => $widget,
                'properties' => [],
            ];
        }

        if ($widget instanceof WidgetConfiguration) {
            return [
                'class' => $widget->widget,
                'properties' => $widget->getProperties(),
            ];
        }

        return null;
    }

    protected function detectWidgetKind(string $widgetClass): string
    {
        return match (true) {
            is_subclass_of($widgetClass, StatsOverviewWidget::class) => 'stats_overview',
            default => 'widget',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizeWidgetStat(Stat $stat): array
    {
        return $this->filterNullValues([
            'label' => $this->normalizeValue($stat->getLabel()),
            'value' => $this->normalizeValue($stat->getValue()),
            'description' => $this->normalizeValue($stat->getDescription()),
            'description_icon' => $this->normalizeValue($stat->getDescriptionIcon()),
            'color' => $this->normalizeValue($stat->getColor()),
            'chart_points' => count($stat->getChart() ?? []),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function summarizeActionAuthorization(object $action, ?string $policyClass = null): ?array
    {
        $authorization = $this->getProtectedProperty($action, 'authorization');
        $authorizationMessage = $this->normalizeValue($this->getProtectedProperty($action, 'authorizationMessage'));
        $authorizationTooltip = $this->getProtectedProperty($action, 'hasAuthorizationTooltip');
        $authorizationNotification = $this->getProtectedProperty($action, 'hasAuthorizationNotification');
        $authorizeIndividualRecords = $this->getProtectedProperty($action, 'authorizeIndividualRecords');
        $policyAbilities = $this->resolvePolicyAbilities($authorization, $action);

        if ($authorization === null && $authorizeIndividualRecords === null && $authorizationMessage === null) {
            return $this->inferDefaultAuthorizationHint($action, $policyClass);
        }

        return $this->filterNullValues([
            'mode' => is_array($authorization) ? ($authorization['type'] ?? 'configured') : ($authorization === null ? 'default' : get_debug_type($authorization)),
            'abilities' => is_array($authorization) ? array_values($authorization['abilities'] ?? []) : null,
            'policy_class' => $policyClass,
            'policy_abilities' => $policyAbilities,
            'arguments' => is_array($authorization)
                ? $this->normalizeValue($this->normalizeAuthorizationArguments($authorization['arguments'] ?? []))
                : ($authorization !== null && ! is_bool($authorization) ? $this->normalizeValue($this->describeAuthorizationValue($authorization)) : null),
            'message' => $authorizationMessage,
            'tooltip' => $this->describeAuthorizationFlag($authorizationTooltip),
            'notification' => $this->describeAuthorizationFlag($authorizationNotification),
            'individual_records' => $this->describeIndividualRecordAuthorization($authorizeIndividualRecords),
            'default_hint' => $authorization === null ? $this->inferDefaultAuthorizationHint($action, $policyClass) : null,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function resolvePolicyAbilities(mixed $authorization, object $action): array
    {
        $abilities = [];

        if (is_array($authorization)) {
            foreach ($authorization['abilities'] ?? [] as $ability) {
                $normalizedAbility = $this->normalizeValue($ability);

                if (is_string($normalizedAbility) && $normalizedAbility !== '') {
                    $abilities[] = $normalizedAbility;
                }
            }
        }

        if ($abilities === [] && $authorization === null) {
            $defaultAbility = $this->inferDefaultActionAbility($action);

            if (is_string($defaultAbility) && $defaultAbility !== '') {
                $abilities[] = $defaultAbility;
            }
        }

        return array_values(array_unique($abilities));
    }

    protected function describeAuthorizationFlag(mixed $flag): mixed
    {
        return match (true) {
            is_bool($flag) => $flag,
            $flag === null => null,
            default => get_debug_type($flag),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function describeIndividualRecordAuthorization(mixed $authorization): ?array
    {
        if ($authorization === null || $authorization === false) {
            return null;
        }

        return match (true) {
            is_string($authorization), $authorization instanceof BackedEnum => [
                'mode' => 'ability',
                'value' => $this->normalizeValue($authorization),
            ],
            is_bool($authorization) => [
                'mode' => 'default_resolver',
                'value' => $authorization,
            ],
            default => [
                'mode' => get_debug_type($authorization),
            ],
        };
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @return array<int, mixed>
     */
    protected function normalizeAuthorizationArguments(array $arguments): array
    {
        return array_values(array_map(fn (mixed $argument): mixed => match (true) {
            $argument instanceof Model => $argument::class,
            is_object($argument) => $argument::class,
            default => $argument,
        }, $arguments));
    }

    protected function describeAuthorizationValue(mixed $authorization): mixed
    {
        return match (true) {
            is_bool($authorization), is_int($authorization), is_float($authorization), is_string($authorization) => $authorization,
            $authorization instanceof BackedEnum => $authorization->value,
            default => get_debug_type($authorization),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function inferDefaultAuthorizationHint(object $action, ?string $policyClass = null): ?array
    {
        $ability = $this->inferDefaultActionAbility($action);

        if ($ability === null) {
            return null;
        }

        return [
            'mode' => 'default',
            'policy_class' => $policyClass,
            'policy_ability' => $ability,
            'policy_abilities' => [$ability],
        ];
    }

    protected function inferDefaultActionAbility(object $action): ?string
    {
        return match (class_basename($action)) {
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
    }

    /**
     * @return array{class: string, source: array<string, mixed>|null, abilities: list<string>}|null
     */
    protected function describeResourcePolicy(?string $modelClass): ?array
    {
        $policyClass = $this->resolvePolicyClass($modelClass);

        if ($policyClass === null) {
            return null;
        }

        return [
            'class' => $policyClass,
            'source' => $this->describeClassSource($policyClass),
            'abilities' => $this->listPolicyAbilities($policyClass),
        ];
    }

    protected function resolvePolicyClass(?string $modelClass): ?string
    {
        if (! is_string($modelClass) || $modelClass === '') {
            return null;
        }

        try {
            $policy = Gate::getPolicyFor($modelClass);

            if (is_object($policy)) {
                return $policy::class;
            }

            if (is_string($policy) && $policy !== '') {
                return $policy;
            }
        } catch (Throwable) {
            // Fall back to Laravel's conventional policy class name if the gate can not resolve it.
        }

        $guessedPolicyClass = 'App\\Policies\\'.class_basename($modelClass).'Policy';

        return class_exists($guessedPolicyClass) ? $guessedPolicyClass : null;
    }

    /**
     * @return list<string>
     */
    protected function listPolicyAbilities(string $policyClass): array
    {
        if (! class_exists($policyClass)) {
            return [];
        }

        $reflection = new ReflectionClass($policyClass);
        $abilities = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $policyClass) {
                continue;
            }

            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue;
            }

            if (Str::startsWith($method->getName(), '__')) {
                continue;
            }

            if (in_array($method->getName(), ['allow', 'before', 'deny', 'denyAsNotFound', 'denyWithStatus'], true)) {
                continue;
            }

            $abilities[] = $method->getName();
        }

        sort($abilities);

        return array_values(array_unique($abilities));
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @param  array<string, mixed>  $table
     * @return list<string>
     */
    protected function collectResourceActionAbilities(array $pages, array $table): array
    {
        $abilities = [];

        foreach ($pages as $page) {
            $abilities = [
                ...$abilities,
                ...$this->collectActionAbilities($page['header_actions'] ?? []),
            ];
        }

        $abilities = [
            ...$abilities,
            ...$this->collectActionAbilities($table['empty_state']['actions'] ?? []),
            ...$this->collectActionAbilities($table['header_actions'] ?? []),
            ...$this->collectActionAbilities($table['record_actions'] ?? []),
        ];

        $abilities = array_values(array_unique(array_filter($abilities, static fn (mixed $ability): bool => is_string($ability) && $ability !== '')));
        sort($abilities);

        return $abilities;
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return list<string>
     */
    protected function collectActionAbilities(array $actions): array
    {
        $abilities = [];

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $authorization = $action['authorization'] ?? null;

            if (is_array($authorization)) {
                foreach ($authorization['policy_abilities'] ?? [] as $ability) {
                    if (is_string($ability) && $ability !== '') {
                        $abilities[] = $ability;
                    }
                }

                $defaultHint = $authorization['default_hint'] ?? null;

                if (is_array($defaultHint)) {
                    $defaultAbility = $defaultHint['policy_ability'] ?? null;

                    if (is_string($defaultAbility) && $defaultAbility !== '') {
                        $abilities[] = $defaultAbility;
                    }
                }
            }

            if (is_array($action['actions'] ?? null)) {
                $abilities = [
                    ...$abilities,
                    ...$this->collectActionAbilities($action['actions']),
                ];
            }
        }

        return $abilities;
    }

    protected function getProtectedProperty(object $object, string $property): mixed
    {
        try {
            $reflection = new ReflectionClass($object);

            while ($reflection) {
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
     * @param  Action[]|ActionGroup[]|Component[]|Column[]|BaseFilter[]  $items
     * @return array<string, int>
     */
    protected function countTypes(array $items): array
    {
        $counts = [];

        foreach ($items as $item) {
            if (! is_object($item)) {
                continue;
            }

            $type = class_basename($item);
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  array<array<string, mixed>, mixed>  $data
     * @return array<string, mixed>
     */
    protected function filterNullValues(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    protected function shouldClassBeMinified(?string $class): bool
    {
        return is_string($class) && $class !== '' && $this->hasAttribute($class, DiscoverShouldMinify::class);
    }

    protected function getMinifyReason(string $class): ?string
    {
        /** @var ?DiscoverShouldMinify $attribute */
        $attribute = $this->getAttributeInstance($class, DiscoverShouldMinify::class);

        return $attribute?->reason;
    }

    protected function hasAttribute(string $class, string $attributeClass): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return $reflection->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    protected function getAttributeInstance(string $class, string $attributeClass): mixed
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @return list<string>
     */
    protected function getAttributeClassNames(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);

        return array_values(array_map(
            static fn (ReflectionAttribute $attribute): string => $attribute->getName(),
            $reflection->getAttributes(),
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function describeClassSource(string $class): ?array
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();

        if (! is_string($file) || $file === '') {
            return null;
        }

        return [
            'file' => $file,
            'start_line' => $reflection->getStartLine(),
            'end_line' => $reflection->getEndLine(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function describeMethodSource(string $class, string $method): ?array
    {
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $reflectionMethod = new ReflectionMethod($class, $method);
        $file = $reflectionMethod->getFileName();

        if (! is_string($file) || $file === '') {
            return null;
        }

        return [
            'file' => $file,
            'start_line' => $reflectionMethod->getStartLine(),
            'end_line' => $reflectionMethod->getEndLine(),
        ];
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Htmlable) {
            return $this->normalizeString($value->toHtml());
        }

        if (is_array($value)) {
            return array_map($this->normalizeValue(...), $value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return $this->normalizeString((string) $value);
        }

        if (is_string($value)) {
            return $this->normalizeString($value);
        }

        return $value;
    }

    protected function normalizeString(string $value): string
    {
        return (string) Str::of(strip_tags($value))
            ->squish()
            ->limit(240, '...');
    }

    protected function callPublicMethod(object|string $target, string $method, bool $requireStatic = false): mixed
    {
        try {
            if (is_string($target)) {
                if (! class_exists($target) || ! method_exists($target, $method)) {
                    return null;
                }

                $reflectionMethod = new ReflectionMethod($target, $method);

                if (! $reflectionMethod->isPublic() || $reflectionMethod->getNumberOfRequiredParameters() > 0) {
                    return null;
                }

                if ($requireStatic && ! $reflectionMethod->isStatic()) {
                    return null;
                }

                return $target::$method();
            }

            if (! method_exists($target, $method)) {
                return null;
            }

            $reflectionMethod = new ReflectionMethod($target, $method);

            if (! $reflectionMethod->isPublic() || $reflectionMethod->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            return $target->{$method}();
        } catch (Throwable) {
            return null;
        }
    }

    protected function callMethodAnyVisibility(object $target, string $method): mixed
    {
        if (! method_exists($target, $method)) {
            return null;
        }

        try {
            $reflectionMethod = new ReflectionMethod($target, $method);

            if ($reflectionMethod->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            if ($reflectionMethod->isPublic()) {
                return $target->{$method}();
            }

            $invoker = Closure::bind(fn (): mixed => $this->{$method}(), $target, $target::class);

            return $invoker instanceof Closure ? $invoker() : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function makeModel(string $modelClass): ?Model
    {
        try {
            /** @var Model $model */
            $model = $this->app->make($modelClass);

            return $model;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<class-string>
     */
    protected function discoverAnnotatedClasses(string $attributeClass): array
    {
        if (! class_exists(Discover::class)) {
            throw new RuntimeException('spatie/php-structure-discoverer is not installed. Run composer update before using accelerator:resource-context.');
        }

        $structures = Discover::in(app_path('Filament'))
            ->classes()
            ->withoutChains()
            ->get();

        $classes = [];

        foreach ($structures as $structure) {
            if (is_string($structure) && class_exists($structure) && $this->hasAttribute($structure, $attributeClass)) {
                $classes[] = $structure;
            }
        }

        sort($classes);

        return array_values(array_unique($classes));
    }
}
