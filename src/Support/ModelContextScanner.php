<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class ModelContextScanner
{
    /**
     * @var array<string, array{
     *     connection: Connection,
     *     schema: Builder,
     *     current_schemas: list<string>,
     *     platform: array<string, mixed>,
     *     tables: list<array<string, mixed>>,
     *     tables_by_qualified_name: array<string, array<string, mixed>>,
     *     table_details: array<string, array<string, mixed>>,
     *     views: list<array<string, mixed>>,
     *     types: list<array<string, mixed>>
     * }>
     */
    protected array $contexts = [];

    protected bool $includeCounts = false;

    protected bool $includeViews = false;

    protected bool $includeTypes = false;

    protected ?string $databaseOverride = null;

    protected ?array $availableModelClasses = null;

    public function __construct(
        protected Application $app,
        protected ConnectionResolverInterface $connections,
        protected ModelInspector $modelInspector,
    ) {}

    /**
     * @return array<string, string|mixed[]>
     */
    public function scan(
        array $requestedModels = [],
        ?string $database = null,
        bool $includeCounts = false,
        bool $includeViews = false,
        bool $includeTypes = false,
    ): array {
        $this->contexts = [];
        $this->includeCounts = $includeCounts;
        $this->includeViews = $includeViews;
        $this->includeTypes = $includeTypes;
        $this->databaseOverride = $database;

        $errors = [];
        $requestedModels = array_values(array_filter($requestedModels));
        $resolvedModels = $this->resolveRequestedModels($requestedModels, $errors);

        if ($requestedModels === []) {
            $resolvedModels = $this->findAvailableModelClasses();
        }

        sort($resolvedModels);

        $models = [];
        $tableToModels = [];

        foreach ($resolvedModels as $modelClass) {
            try {
                $modelData = $this->scanModel($modelClass);
            } catch (Throwable $e) {
                $errors[] = [
                    'model' => $modelClass,
                    'message' => $e->getMessage(),
                ];

                continue;
            }

            $models[] = $modelData;
            $tableToModels[$modelData['database']][$modelData['table']][] = $modelClass;
        }

        ksort($tableToModels);

        foreach ($tableToModels as &$modelsByTable) {
            ksort($modelsByTable);

            foreach ($modelsByTable as &$modelClasses) {
                sort($modelClasses);
            }
        }

        $databases = [];

        foreach ($this->contexts as $connectionName => $context) {
            $databases[$connectionName] = [
                'platform' => $context['platform'],
                'tables' => $context['tables'],
                'views' => $context['views'],
                'types' => $context['types'],
            ];
        }

        ksort($databases);

        $unmappedTables = $this->buildUnmappedTables($databases, $tableToModels);

        return [
            'generated_at' => now()->toIso8601String(),
            'requested_models' => $requestedModels,
            'databases' => $databases,
            'models' => $models,
            'table_to_models' => $tableToModels,
            'unmapped_tables' => $unmappedTables,
            'errors' => $errors,
            'summary' => [
                'connections_scanned' => count($databases),
                'models_requested' => count($requestedModels),
                'models_scanned' => count($models),
                'tables_discovered' => array_sum(array_map(static fn (array $database): int => count($database['tables']), $databases)),
                'tables_without_models' => count($unmappedTables),
                'models_missing_tables' => count(array_filter($models, static fn (array $model): bool => ! $model['table_exists'])),
                'models_with_inspection_errors' => count(array_filter($models, static fn (array $model): bool => $model['inspection_error'] !== null)),
                'models_with_cast_drift' => count(array_filter($models, fn (array $model): bool => $model['diagnostics']['casts_missing_columns'] !== []
                    || $model['diagnostics']['decimal_columns_without_big_decimal_casts'] !== []
                    || $model['diagnostics']['date_columns_without_immutable_casts'] !== []
                    || $model['diagnostics']['json_columns_without_structured_casts'] !== [])),
                'models_with_relation_drift' => count(array_filter($models, static fn (array $model): bool => $model['diagnostics']['foreign_keys_without_relations'] !== [] || $model['diagnostics']['relations_missing_columns'] !== [])),
                'models_with_relation_errors' => count(array_filter($models, static fn (array $model): bool => $model['diagnostics']['relations_with_errors'] !== [])),
                'errors' => count($errors),
            ],
        ];
    }

    /**
     * @phpstan-impure
     *
     * @return array<string, mixed>
     */
    protected function scanModel(string $modelClass): array
    {
        /** @var Model $model */
        $model = $this->app->make($modelClass);

        if ($this->databaseOverride !== null) {
            $model->setConnection($this->databaseOverride);
        }

        $connectionName = $model->getConnection()->getName();
        $this->getContext($connectionName);

        $tableName = $model->getTable();
        $tableInfo = $this->resolveTable($connectionName, $tableName);
        $tableSchema = $tableInfo ? $this->getTableDetails($connectionName, $tableName) : null;

        $inspectionError = null;
        $modelInfo = null;

        try {
            $modelInfo = $this->modelInspector->inspect($modelClass, $this->databaseOverride);
        } catch (Throwable $throwable) {
            $inspectionError = $throwable->getMessage();
        }

        $relationSummaries = [];

        if ($modelInfo !== null) {
            foreach ($modelInfo->relations->values()->all() as $relationSummary) {
                $relationSummaries[$relationSummary['name']] = $relationSummary;
            }
        }

        foreach ($this->discoverRelationSummaries($model) as $relationSummary) {
            $relationSummaries[$relationSummary['name']] = $relationSummary;
        }

        ksort($relationSummaries);

        $relations = array_map(
            fn (array $relationSummary): array => $this->describeRelation($model, $relationSummary),
            array_values($relationSummaries),
        );

        $casts = $model->getCasts();
        ksort($casts);

        $effectiveCasts = $casts;

        if ($modelInfo !== null) {
            foreach ($modelInfo->attributes->values()->all() as $attribute) {
                if ($attribute['cast'] === null) {
                    continue;
                }

                $effectiveCasts[$attribute['name']] = $attribute['cast'];
            }
        }

        return [
            'class' => $modelClass,
            'short_name' => class_basename($modelClass),
            'database' => $connectionName,
            'table' => $tableName,
            'schema_table' => $tableInfo['schema_qualified_name'] ?? $tableName,
            'table_exists' => $tableSchema !== null,
            'primary_key' => $model->getKeyName(),
            'incrementing' => $model->getIncrementing(),
            'timestamps' => $model->usesTimestamps(),
            'uses_soft_deletes' => in_array(SoftDeletes::class, class_uses_recursive($modelClass), true),
            'policy' => $modelInfo?->policy,
            'collection' => $modelInfo?->collection,
            'builder' => $modelInfo?->builder,
            'resource' => $modelInfo?->resource,
            'hidden' => array_values($model->getHidden()),
            'appends' => array_values($model->getAppends()),
            'casts' => $casts,
            'attributes' => $modelInfo?->attributes?->values()->all() ?? [],
            'events' => $modelInfo?->events?->values()->all() ?? [],
            'observers' => $modelInfo?->observers?->values()->all() ?? [],
            'relations' => $relations,
            'schema' => $tableSchema,
            'inspection_error' => $inspectionError,
            'diagnostics' => $this->buildDiagnostics($model, $tableSchema, $casts, $effectiveCasts, $relations),
        ];
    }

    protected function resolveRequestedModels(array $requestedModels, array &$errors): array
    {
        $resolvedModels = [];

        foreach ($requestedModels as $requestedModel) {
            $modelClass = $this->qualifyModel($requestedModel);

            if ($modelClass === null) {
                $errors[] = [
                    'model' => $requestedModel,
                    'message' => 'Model class not found.',
                ];

                continue;
            }

            $reflection = new ReflectionClass($modelClass);

            if ($reflection->isAbstract()) {
                $errors[] = [
                    'model' => $requestedModel,
                    'message' => 'Abstract models can not be scanned directly.',
                ];

                continue;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                $errors[] = [
                    'model' => $requestedModel,
                    'message' => 'Resolved class is not an Eloquent model.',
                ];

                continue;
            }

            $resolvedModels[] = $modelClass;
        }

        return array_values(array_unique($resolvedModels));
    }

    protected function qualifyModel(string $model): ?string
    {
        $normalizedModel = str_replace('/', '\\', ltrim($model, '\\/'));

        if (class_exists($normalizedModel)) {
            return $normalizedModel;
        }

        $candidate = 'App\\Models\\'.$normalizedModel;

        if (class_exists($candidate)) {
            return $candidate;
        }

        $matches = array_values(array_filter(
            $this->findAvailableModelClasses(),
            static fn (string $availableModel): bool => class_basename($availableModel) === $normalizedModel,
        ));

        return count($matches) === 1 ? $matches[0] : null;
    }

    protected function findAvailableModelClasses(): array
    {
        if ($this->availableModelClasses !== null) {
            return $this->availableModelClasses;
        }

        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return $this->availableModelClasses = [];
        }

        $availableModels = [];

        foreach (File::allFiles($modelPath) as $file) {
            $relativePath = str_replace($modelPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $class = 'App\\Models\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath,
            );

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $availableModels[] = $class;
        }

        sort($availableModels);

        return $this->availableModelClasses = $availableModels;
    }

    protected function discoverRelationSummaries(Model $model): array
    {
        $relationSummaries = [];
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract() || $method->isConstructor()) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            if (! str_starts_with($method->getDeclaringClass()->getName(), 'App\\Models\\')) {
                continue;
            }

            try {
                $relation = Relation::noConstraints(fn (): mixed => $method->invoke($model));
            } catch (Throwable) {
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            $relationSummaries[] = [
                'name' => $method->getName(),
                'type' => class_basename($relation),
                'related' => $relation instanceof MorphTo ? null : $relation->getRelated()::class,
            ];
        }

        return $relationSummaries;
    }

    /**
     * @param  array<string, mixed>  $relationSummary
     */
    protected function describeRelation(Model $model, array $relationSummary): array
    {
        $modelConnection = $model->getConnection()->getName();
        $modelTable = $model->getTable();
        $relationName = $relationSummary['name'];

        try {
            $relation = Relation::noConstraints(fn () => $model->{$relationName}());
        } catch (Throwable $throwable) {
            return [
                'name' => $relationName,
                'relation_name' => $relationName,
                'type' => $relationSummary['type'] ?? null,
                'related_model' => $relationSummary['related'] ?? null,
                'related_table' => null,
                'related_connection' => null,
                'dynamic_related_model' => ($relationSummary['type'] ?? null) === 'MorphTo',
                'columns' => [],
                'error' => $throwable->getMessage(),
            ];
        }

        if (! $relation instanceof Relation) {
            return [
                'name' => $relationName,
                'relation_name' => $relationName,
                'type' => $relationSummary['type'] ?? null,
                'related_model' => $relationSummary['related'] ?? null,
                'related_table' => null,
                'related_connection' => null,
                'dynamic_related_model' => ($relationSummary['type'] ?? null) === 'MorphTo',
                'columns' => [],
                'error' => 'Method did not return an Eloquent relation.',
            ];
        }

        $relatedModelClass = $this->resolveRelatedModelClass($relationSummary['related'] ?? null, $relation);
        $relatedConnection = $relation instanceof MorphTo && $relatedModelClass === null
            ? null
            : $relation->getRelated()->getConnection()->getName();
        $relatedTable = $relation instanceof MorphTo && $relatedModelClass === null
            ? null
            : $relation->getRelated()->getTable();

        $description = [
            'name' => $relationName,
            'relation_name' => method_exists($relation, 'getRelationName') ? $relation->getRelationName() : $relationName,
            'type' => class_basename($relation),
            'related_model' => $relatedModelClass,
            'related_table' => $relatedTable,
            'related_connection' => $relatedConnection,
            'dynamic_related_model' => $relation instanceof MorphTo,
            'columns' => [],
        ];

        if ($relation instanceof MorphTo) {
            $description['columns'] = array_filter([
                'foreign_key' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getForeignKeyName(),
                    $relation->getQualifiedForeignKeyName(),
                ),
                'owner_key' => $relatedTable !== null && $relation->getQualifiedOwnerKeyName() !== ''
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getOwnerKeyName(),
                        $relation->getQualifiedOwnerKeyName(),
                    )
                    : null,
                'morph_type' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getMorphType(),
                    $model->qualifyColumn($relation->getMorphType()),
                ),
            ]);

            $description['morph'] = [
                'type_column' => $relation->getMorphType(),
            ];

            return $description;
        }

        if ($relation instanceof MorphToMany) {
            $description['columns'] = array_filter([
                'foreign_pivot_key' => $this->makeColumnDescriptor(
                    $relatedConnection,
                    $relation->getTable(),
                    $relation->getForeignPivotKeyName(),
                    $relation->getQualifiedForeignPivotKeyName(),
                ),
                'related_pivot_key' => $this->makeColumnDescriptor(
                    $relatedConnection,
                    $relation->getTable(),
                    $relation->getRelatedPivotKeyName(),
                    $relation->getQualifiedRelatedPivotKeyName(),
                ),
                'parent_key' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getParentKeyName(),
                    $relation->getQualifiedParentKeyName(),
                ),
                'related_key' => $relatedTable !== null
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getRelatedKeyName(),
                        $relation->getQualifiedRelatedKeyName(),
                    )
                    : null,
                'morph_type' => $this->makeColumnDescriptor(
                    $relatedConnection,
                    $relation->getTable(),
                    $relation->getMorphType(),
                    $relation->getQualifiedMorphTypeName(),
                ),
            ]);

            $description['pivot'] = [
                'table' => $relation->getTable(),
                'accessor' => $relation->getPivotAccessor(),
                'columns' => array_values($relation->getPivotColumns()),
            ];

            $description['morph'] = [
                'type_column' => $relation->getMorphType(),
                'class' => $relation->getMorphClass(),
                'inverse' => $relation->getInverse(),
            ];

            return $description;
        }

        if ($relation instanceof BelongsToMany) {
            $description['columns'] = array_filter([
                'foreign_pivot_key' => $this->makeColumnDescriptor(
                    $relatedConnection,
                    $relation->getTable(),
                    $relation->getForeignPivotKeyName(),
                    $relation->getQualifiedForeignPivotKeyName(),
                ),
                'related_pivot_key' => $this->makeColumnDescriptor(
                    $relatedConnection,
                    $relation->getTable(),
                    $relation->getRelatedPivotKeyName(),
                    $relation->getQualifiedRelatedPivotKeyName(),
                ),
                'parent_key' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getParentKeyName(),
                    $relation->getQualifiedParentKeyName(),
                ),
                'related_key' => $relatedTable !== null
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getRelatedKeyName(),
                        $relation->getQualifiedRelatedKeyName(),
                    )
                    : null,
            ]);

            $description['pivot'] = [
                'table' => $relation->getTable(),
                'accessor' => $relation->getPivotAccessor(),
                'columns' => array_values($relation->getPivotColumns()),
            ];

            return $description;
        }

        if ($relation instanceof MorphOneOrMany) {
            $description['columns'] = array_filter([
                'foreign_key' => $relatedTable !== null
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getForeignKeyName(),
                        $relation->getQualifiedForeignKeyName(),
                    )
                    : null,
                'local_key' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getLocalKeyName(),
                    $relation->getQualifiedParentKeyName(),
                ),
                'morph_type' => $relatedTable !== null
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getMorphType(),
                        $relatedTable.'.'.$relation->getMorphType(),
                    )
                    : null,
            ]);

            $description['morph'] = [
                'type_column' => $relation->getMorphType(),
                'class' => $relation->getMorphClass(),
            ];

            return $description;
        }

        if ($relation instanceof HasOneOrManyThrough) {
            $throughTable = $this->extractTableName($relation->getQualifiedFirstKeyName())
                ?? $this->extractTableName($relation->getQualifiedParentKeyName());

            $description['columns'] = array_filter([
                'first_key' => $throughTable !== null
                    ? $this->makeColumnDescriptor(
                        $modelConnection,
                        $throughTable,
                        $relation->getFirstKeyName(),
                        $relation->getQualifiedFirstKeyName(),
                    )
                    : null,
                'second_key' => $relatedTable !== null
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getForeignKeyName(),
                        $relation->getQualifiedForeignKeyName(),
                    )
                    : null,
                'local_key' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getLocalKeyName(),
                    $relation->getQualifiedLocalKeyName(),
                ),
                'second_local_key' => $throughTable !== null
                    ? $this->makeColumnDescriptor(
                        $modelConnection,
                        $throughTable,
                        $relation->getSecondLocalKeyName(),
                        $relation->getQualifiedParentKeyName(),
                    )
                    : null,
            ]);

            $description['through'] = [
                'table' => $throughTable,
                'connection' => $modelConnection,
            ];

            return $description;
        }

        if ($relation instanceof HasOneOrMany) {
            $description['columns'] = array_filter([
                'foreign_key' => $relatedTable !== null
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getForeignKeyName(),
                        $relation->getQualifiedForeignKeyName(),
                    )
                    : null,
                'local_key' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getLocalKeyName(),
                    $relation->getQualifiedParentKeyName(),
                ),
            ]);

            return $description;
        }

        if ($relation instanceof BelongsTo) {
            $description['columns'] = array_filter([
                'foreign_key' => $this->makeColumnDescriptor(
                    $modelConnection,
                    $modelTable,
                    $relation->getForeignKeyName(),
                    $relation->getQualifiedForeignKeyName(),
                ),
                'owner_key' => $relatedTable !== null
                    ? $this->makeColumnDescriptor(
                        $relatedConnection,
                        $relatedTable,
                        $relation->getOwnerKeyName(),
                        $relation->getQualifiedOwnerKeyName(),
                    )
                    : null,
            ]);

            return $description;
        }

        return $description;
    }

    protected function resolveRelatedModelClass(?string $summaryClass, Relation $relation): ?string
    {
        $relatedClass = $summaryClass ?? $relation->getRelated()::class;

        return $relatedClass === Model::class ? null : $relatedClass;
    }

    /**
     * @param  array<int, mixed[]>  $relations
     */
    protected function buildDiagnostics(Model $model, ?array $tableSchema, array $declaredCasts, array $effectiveCasts, array $relations): array
    {
        $diagnostics = [
            'table_missing' => $tableSchema === null,
            'casts_missing_columns' => [],
            'decimal_columns_without_big_decimal_casts' => [],
            'date_columns_without_immutable_casts' => [],
            'json_columns_without_structured_casts' => [],
            'foreign_keys_without_relations' => [],
            'relations_missing_columns' => [],
            'relations_with_errors' => [],
        ];

        foreach ($relations as $relation) {
            if (isset($relation['error'])) {
                $diagnostics['relations_with_errors'][] = [
                    'relation' => $relation['name'],
                    'message' => $relation['error'],
                ];
            }

            foreach ($relation['columns'] as $role => $descriptor) {
                if (($descriptor['exists'] ?? null) === false) {
                    $diagnostics['relations_missing_columns'][] = [
                        'relation' => $relation['name'],
                        'role' => $role,
                        'table' => $descriptor['table'],
                        'column' => $descriptor['column'],
                    ];
                }
            }
        }

        if ($tableSchema === null) {
            $diagnostics['casts_missing_columns'] = array_keys($declaredCasts);

            return $diagnostics;
        }

        $tableColumns = [];

        foreach ($tableSchema['columns'] as $column) {
            $tableColumns[$column['column']] = $column;
        }

        foreach ($declaredCasts as $column => $cast) {
            if (! array_key_exists($column, $tableColumns)) {
                $diagnostics['casts_missing_columns'][] = $column;
            }
        }

        foreach ($tableColumns as $columnName => $column) {
            $cast = $effectiveCasts[$columnName] ?? null;
            $normalizedCast = $cast === null ? null : strtolower((string) $cast);
            $normalizedType = strtolower((string) $column['type']);

            if (
                in_array($normalizedType, ['decimal', 'numeric', 'float', 'double'], true)
                && ! str_contains((string) $cast, 'BigDecimalCast')
            ) {
                $diagnostics['decimal_columns_without_big_decimal_casts'][] = [
                    'column' => $columnName,
                    'type' => $column['type'],
                    'cast' => $cast,
                ];
            }

            if (
                in_array($normalizedType, ['date', 'datetime', 'timestamp'], true)
                && ! in_array($columnName, ['created_at', 'updated_at'], true)
                && ($normalizedCast === null || (! str_contains($normalizedCast, 'immutable') && ! str_contains((string) $cast, 'CarbonImmutable')))
            ) {
                $diagnostics['date_columns_without_immutable_casts'][] = [
                    'column' => $columnName,
                    'type' => $column['type'],
                    'cast' => $cast,
                ];
            }

            if ($normalizedType === 'json' && ($normalizedCast === null || ! $this->usesStructuredJsonCast($normalizedCast))) {
                $diagnostics['json_columns_without_structured_casts'][] = [
                    'column' => $columnName,
                    'type' => $column['type'],
                    'cast' => $cast,
                ];
            }
        }

        $relationForeignKeys = [];

        foreach ($relations as $relation) {
            $foreignKey = $relation['columns']['foreign_key'] ?? null;

            if ($foreignKey === null) {
                continue;
            }

            if ($foreignKey['table'] !== $model->getTable()) {
                continue;
            }

            $relationForeignKeys[$foreignKey['column']] = true;
        }

        foreach ($tableSchema['foreign_keys'] as $foreignKey) {
            $isMapped = count($foreignKey['columns']) === 1
                && isset($relationForeignKeys[$foreignKey['columns'][0]]);

            if (! $isMapped) {
                $diagnostics['foreign_keys_without_relations'][] = $foreignKey;
            }
        }

        return $diagnostics;
    }

    protected function usesStructuredJsonCast(string $normalizedCast): bool
    {
        foreach (['array', 'json', 'collection', 'object'] as $expectedSegment) {
            if (str_contains($normalizedCast, $expectedSegment)) {
                return true;
            }
        }

        return false;
    }

    protected function makeColumnDescriptor(
        ?string $connectionName,
        ?string $table,
        ?string $column,
        ?string $qualified = null,
    ): ?array {
        if ($table === null || $column === null || $column === '') {
            return null;
        }

        return [
            'table' => $table,
            'column' => $column,
            'qualified' => $qualified ?? $table.'.'.$column,
            'exists' => $connectionName === null ? null : $this->columnExists($connectionName, $table, $column),
        ];
    }

    protected function columnExists(string $connectionName, string $table, string $column): ?bool
    {
        $tableSchema = $this->getTableDetails($connectionName, $table);

        if ($tableSchema === null) {
            return null;
        }

        foreach ($tableSchema['columns'] as $tableColumn) {
            if ($tableColumn['column'] === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $tableToModels
     * @param  array<string, array<string, array<int|string, mixed>>>  $databases
     */
    protected function buildUnmappedTables(array $databases, array $tableToModels): array
    {
        $unmappedTables = [];

        foreach ($databases as $connectionName => $database) {
            foreach ($database['tables'] as $table) {
                if (! empty($tableToModels[$connectionName][$table['table']])) {
                    continue;
                }

                $unmappedTables[] = [
                    'database' => $connectionName,
                    'schema' => $table['schema'],
                    'table' => $table['table'],
                    'schema_qualified_name' => $table['schema_qualified_name'],
                ];
            }
        }

        return $unmappedTables;
    }

    protected function &getContext(string $connectionName): array
    {
        if (! isset($this->contexts[$connectionName])) {
            /** @var Connection $connection */
            $connection = $this->connections->connection($connectionName);
            $schema = $connection->getSchemaBuilder();
            $tables = $this->buildTables($connection, $schema);

            $this->contexts[$connectionName] = [
                'connection' => $connection,
                'schema' => $schema,
                'current_schemas' => Arr::wrap($schema->getCurrentSchemaListing() ?? $schema->getCurrentSchemaName()),
                'platform' => [
                    'config' => Arr::except(config('database.connections.'.$connectionName, []), ['password']),
                    'name' => $connection->getDriverTitle(),
                    'connection' => $connection->getName(),
                    'version' => $connection->getServerVersion(),
                    'open_connections' => $connection->threadCount(),
                ],
                'tables' => $tables,
                'tables_by_qualified_name' => $this->keyTablesByQualifiedName($tables),
                'table_details' => [],
                'views' => $this->includeViews ? $this->buildViews($connection, $schema) : [],
                'types' => $this->includeTypes ? $this->buildTypes($schema) : [],
            ];
        }

        return $this->contexts[$connectionName];
    }

    protected function buildTables(Connection $connection, Builder $schema): array
    {
        $tables = [];

        foreach ($schema->getTables() as $table) {
            $tables[] = [
                'table' => $table['name'],
                'schema' => $table['schema'],
                'schema_qualified_name' => $table['schema_qualified_name'],
                'size' => $table['size'],
                'rows' => $this->includeCounts
                    ? $connection->withoutTablePrefix(fn ($connection) => $connection->table($table['schema_qualified_name'])->count())
                    : null,
                'engine' => $table['engine'],
                'collation' => $table['collation'],
                'comment' => $table['comment'],
            ];
        }

        return $tables;
    }

    protected function buildViews(Connection $connection, Builder $schema): array
    {
        $views = [];

        foreach ($schema->getViews() as $view) {
            $views[] = [
                'view' => $view['name'],
                'schema' => $view['schema'],
                'rows' => $connection->withoutTablePrefix(fn ($connection) => $connection->table($view['schema_qualified_name'])->count()),
            ];
        }

        return $views;
    }

    protected function buildTypes(Builder $schema): array
    {
        $types = [];

        foreach ($schema->getTypes() as $type) {
            $types[] = [
                'name' => $type['name'],
                'schema' => $type['schema'],
                'type' => $type['type'],
                'category' => $type['category'],
            ];
        }

        return $types;
    }

    protected function keyTablesByQualifiedName(array $tables): array
    {
        $keyedTables = [];

        foreach ($tables as $table) {
            $keyedTables[$table['schema_qualified_name']] = $table;
        }

        return $keyedTables;
    }

    protected function resolveTable(string $connectionName, string $tableName): ?array
    {
        $context = &$this->getContext($connectionName);

        if (isset($context['tables_by_qualified_name'][$tableName])) {
            return $context['tables_by_qualified_name'][$tableName];
        }

        $tables = $context['tables'];
        $currentSchemas = $context['current_schemas'];

        usort($tables, static function (array $left, array $right) use ($currentSchemas): int {
            $leftIndex = array_search($left['schema'], $currentSchemas, true);
            $rightIndex = array_search($right['schema'], $currentSchemas, true);

            $leftScore = $leftIndex === false ? PHP_INT_MAX : $leftIndex;
            $rightScore = $rightIndex === false ? PHP_INT_MAX : $rightIndex;

            return $leftScore <=> $rightScore;
        });

        foreach ($tables as $table) {
            if ($table['table'] === $tableName) {
                return $table;
            }
        }

        return null;
    }

    protected function getTableDetails(string $connectionName, string $tableName): ?array
    {
        $context = &$this->getContext($connectionName);
        $table = $this->resolveTable($connectionName, $tableName);

        if ($table === null) {
            return null;
        }

        $cacheKey = $table['schema_qualified_name'];

        if (array_key_exists($cacheKey, $context['table_details'])) {
            return $context['table_details'][$cacheKey];
        }

        $context['table_details'][$cacheKey] = $context['connection']->withoutTablePrefix(function ($connection) use ($table): array {
            $schema = $connection->getSchemaBuilder();
            $tableName = $table['schema_qualified_name'];

            return [
                'table' => [
                    'schema' => $table['schema'],
                    'name' => $table['table'],
                    'schema_qualified_name' => $table['schema_qualified_name'],
                    'columns' => count($schema->getColumns($tableName)),
                    'size' => $table['size'],
                    'comment' => $table['comment'],
                    'collation' => $table['collation'],
                    'engine' => $table['engine'],
                ],
                'columns' => $this->formatColumns($schema, $tableName),
                'indexes' => $this->formatIndexes($schema, $tableName),
                'foreign_keys' => $this->formatForeignKeys($schema, $tableName),
            ];
        });

        return $context['table_details'][$cacheKey];
    }

    protected function formatColumns(Builder $schema, string $tableName): array
    {
        $columns = [];

        foreach ($schema->getColumns($tableName) as $column) {
            $attributes = array_values(array_filter([
                $column['type_name'],
                $column['generation'] ? $column['generation']['type'] : null,
                $column['auto_increment'] ? 'autoincrement' : null,
                $column['nullable'] ? 'nullable' : null,
                Arr::get($column, 'collation'),
            ]));

            $columns[] = [
                'column' => $column['name'],
                'attributes' => $attributes,
                'default' => $column['default'],
                'type' => $column['type'],
            ];
        }

        return $columns;
    }

    protected function formatIndexes(Builder $schema, string $tableName): array
    {
        $indexes = [];

        foreach ($schema->getIndexes($tableName) as $index) {
            $indexes[] = [
                'name' => $index['name'],
                'columns' => $index['columns'],
                'attributes' => array_values(array_filter([
                    $index['type'],
                    count($index['columns']) > 1 ? 'compound' : null,
                    $index['unique'] && ! $index['primary'] ? 'unique' : null,
                    $index['primary'] ? 'primary' : null,
                ])),
            ];
        }

        return $indexes;
    }

    protected function formatForeignKeys(Builder $schema, string $tableName): array
    {
        $foreignKeys = [];

        foreach ($schema->getForeignKeys($tableName) as $foreignKey) {
            $foreignKeys[] = [
                'name' => $foreignKey['name'],
                'columns' => $foreignKey['columns'],
                'foreign_schema' => $foreignKey['foreign_schema'],
                'foreign_table' => $foreignKey['foreign_table'],
                'foreign_columns' => $foreignKey['foreign_columns'],
                'on_update' => $foreignKey['on_update'],
                'on_delete' => $foreignKey['on_delete'],
            ];
        }

        return $foreignKeys;
    }

    protected function extractTableName(string $qualifiedColumn): ?string
    {
        $separatorPosition = strrpos($qualifiedColumn, '.');

        if ($separatorPosition === false) {
            return null;
        }

        return substr($qualifiedColumn, 0, $separatorPosition);
    }
}
