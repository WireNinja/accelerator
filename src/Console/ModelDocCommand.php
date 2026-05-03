<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;
use WireNinja\Accelerator\Console\Concerns\HasBanner;
use WireNinja\Accelerator\Database\Casts\BigDecimalCast;
use WireNinja\Accelerator\Model\Concerns\HasTypedColumnMethods;
use WireNinja\Accelerator\Support\TypeCaster;

use function Laravel\Prompts\search;

#[Signature('accelerator:model-doc {model? : The model class name (e.g., User or App\Models\User)} {--all : Document all models in app/Models} {--write : Write the PHPDoc directly to the file}')]
#[Description('Generate refined PHPDoc property blocks from schema metadata and cast overrides')]
class ModelDocCommand extends Command
{
    use HasBanner;

    public function handle(): int
    {
        $this->displayBanner();

        if ($this->option('all')) {
            return $this->handleAllModels();
        }

        $modelInput = $this->argument('model');

        if (! $modelInput) {
            $modelInput = $this->searchForModel();
        }

        if (! $modelInput) {
            return 0;
        }

        return $this->documentModel($modelInput);
    }

    protected function documentModel(string $modelInput): int
    {
        $modelClass = $this->qualifyModel($modelInput);

        if (! class_exists($modelClass)) {
            $this->components->error("Model class [{$modelClass}] not found.");

            return 1;
        }

        try {
            /** @var Model $modelInstance */
            $modelInstance = new $modelClass;
            $reflection = new ReflectionClass($modelClass);

            $this->components->info("Generating PHPDoc for: <fg=cyan>{$modelClass}</>");
            $this->newLine();

            $docBlock = $this->generateDocBlock($modelInstance, $reflection);

            if ($this->option('write')) {
                $this->writeToModel($reflection, $docBlock);
                $this->components->success("PHPDoc block successfully written to [{$modelClass}].");
            } else {
                $this->line('Add the following PHPDoc block to your model:');
                $this->newLine();
                $this->info($docBlock);
            }
        } catch (Throwable $e) {
            $this->components->error("Failed to generate PHPDoc for [{$modelClass}]: {$e->getMessage()}");

            return 1;
        }

        return 0;
    }

    protected function handleAllModels(): int
    {
        if (! $this->option('write')) {
            $this->components->error('--write flag is required when using --all.');

            return 1;
        }

        foreach ($this->getAllModelsUnderApp() as $modelName) {
            $this->documentModel($modelName);
        }

        $this->components->success('All models have been documented.');

        return 0;
    }

    /**
     * @return array<int, string>
     */
    protected function getAllModelsUnderApp(): array
    {
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return [];
        }

        return collect(File::allFiles($modelPath))
            ->map(fn (SplFileInfo $file): string => str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
            ->sort()
            ->values()
            ->toArray();
    }

    protected function generateDocBlock(Model $model, ReflectionClass $reflection): string
    {
        $properties = [];
        $methods = [];
        $usesTypedColumnMethods = $this->usesTypedColumnMethods($reflection);

        foreach ($this->getSchemaColumns($model->getTable()) as $column) {
            $columnName = TypeCaster::strictString($column['name'] ?? null);
            $phpType = $this->resolveColumnPhpType($model, $columnName, $column);

            $properties[] = "@property {$phpType} \${$columnName}";

            if ($usesTypedColumnMethods) {
                $methods[] = $this->formatGetterMethod($columnName, $phpType);
                $methods[] = $this->formatSetterMethod($columnName, $phpType);
            }
        }

        foreach ($this->getRelationships($model, $reflection) as $name => $info) {
            $properties[] = "@property {$info['type']} \${$name}";
        }

        $doc = "/**\n";

        foreach (array_values(array_unique($properties)) as $property) {
            $doc .= " * {$property}\n";
        }

        if ($methods !== []) {
            foreach (array_values(array_unique($methods)) as $method) {
                $doc .= " * {$method}\n";
            }
        }

        $doc .= ' */';

        return $doc;
    }

    protected function usesTypedColumnMethods(ReflectionClass $reflection): bool
    {
        return in_array(HasTypedColumnMethods::class, class_uses_recursive($reflection->getName()), true);
    }

    protected function formatGetterMethod(string $columnName, string $phpType): string
    {
        return '@method '.$phpType.' getColumn'.Str::studly($columnName).'()';
    }

    protected function formatSetterMethod(string $columnName, string $phpType): string
    {
        return '@method $this setColumn'.Str::studly($columnName).'('.$phpType.' $value)';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getSchemaColumns(string $table): array
    {
        try {
            /** @var array<int, array<string, mixed>> $columns */
            $columns = Schema::getColumns($table);

            return $columns;
        } catch (Throwable) {
            try {
                return array_map(
                    fn (string $column): array => [
                        'name' => $column,
                        'type' => Schema::getColumnType($table, $column),
                        'nullable' => false,
                    ],
                    Schema::getColumnListing($table),
                );
            } catch (Throwable) {
                return [];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schemaColumn
     */
    protected function resolveColumnPhpType(Model $model, string $column, array $schemaColumn): string
    {
        $schemaType = $this->resolveSchemaPhpType($schemaColumn);
        $castType = $this->resolveCastPhpType($model, $column);
        $nullable = TypeCaster::safeBool($schemaColumn['nullable'] ?? false);

        return $this->applyNullability($castType ?? $schemaType, $nullable);
    }

    /**
     * @param  array<string, mixed>  $schemaColumn
     */
    protected function resolveSchemaPhpType(array $schemaColumn): string
    {
        $databaseType = strtolower(TypeCaster::safeString($schemaColumn['type_name'] ?? $schemaColumn['type'] ?? 'mixed', 'mixed'));

        return match ($databaseType) {
            'bigint', 'integer', 'int', 'mediumint', 'smallint', 'tinyint' => 'int',
            'bool', 'boolean' => 'bool',
            'decimal', 'double', 'float', 'numeric', 'real' => '\\'.BigDecimal::class,
            'date', 'datetime', 'datetimetz', 'time', 'timestamp', 'timestamptz' => '\\'.Carbon::class,
            'json', 'jsonb' => 'array',
            default => 'string',
        };
    }

    protected function resolveCastPhpType(Model $model, string $column): ?string
    {
        $casts = $model->getCasts();

        if (! array_key_exists($column, $casts)) {
            return null;
        }

        $castDefinition = TypeCaster::safeString($casts[$column] ?? null);

        if ($castDefinition === '') {
            return null;
        }

        [$castBase, $castArguments] = $this->splitCastDefinition($castDefinition);

        if (enum_exists($castBase)) {
            return '\\'.$castBase;
        }

        if ($castBase === BigDecimalCast::class) {
            return '\\'.BigDecimal::class;
        }

        if (is_subclass_of($castBase, CastsAttributes::class) && str_ends_with($castBase, 'BigDecimalCast')) {
            return '\\'.BigDecimal::class;
        }

        if (str_ends_with($castBase, 'AsEnumCollection') && isset($castArguments[0]) && enum_exists($castArguments[0])) {
            return '\\'.SupportCollection::class.'<int, \\'.$castArguments[0].'>';
        }

        if (str_ends_with($castBase, 'AsEnumArrayObject') && isset($castArguments[0]) && enum_exists($castArguments[0])) {
            return 'array<int, \\'.$castArguments[0].'>';
        }

        if (str_ends_with($castBase, 'AsCollection')) {
            return '\\'.SupportCollection::class;
        }

        if (str_ends_with($castBase, 'AsArrayObject')) {
            return 'array';
        }

        return match ($castBase) {
            'array', 'json', 'object' => 'array',
            'bool', 'boolean' => 'bool',
            'collection' => '\\'.SupportCollection::class,
            'custom_datetime', 'date', 'datetime', 'timestamp' => '\\'.Carbon::class,
            'decimal', 'double', 'float', 'real' => '\\'.BigDecimal::class,
            'encrypted' => match ($castArguments[0] ?? null) {
                'array', 'collection', 'json', 'object' => 'array',
                default => 'string',
            },
            'hashed', 'string' => 'string',
            'immutable_custom_datetime', 'immutable_date', 'immutable_datetime' => '\\'.CarbonImmutable::class,
            'int', 'integer' => 'int',
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    protected function splitCastDefinition(string $castDefinition): array
    {
        [$castBase, $arguments] = array_pad(explode(':', $castDefinition, 2), 2, null);

        return [
            ltrim(trim($castBase), '\\'),
            collect(explode(',', $arguments ?? ''))
                ->map(fn (string $argument): string => trim($argument))
                ->filter(fn (string $argument): bool => $argument !== '')
                ->values()
                ->all(),
        ];
    }

    protected function applyNullability(string $type, bool $nullable): string
    {
        if ((! $nullable) || ($type === 'mixed') || str_contains($type, 'null')) {
            return $type;
        }

        return $type.'|null';
    }

    /**
     * @return array<string, array{type: string}>
     */
    protected function getRelationships(Model $model, ReflectionClass $reflection): array
    {
        $relationships = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (in_array($method->getDeclaringClass()->getName(), [
                Model::class,
                'Illuminate\\Database\\Eloquent\\Model',
                'Illuminate\\Foundation\\Auth\\User',
                'WireNinja\\Accelerator\\Model\\AcceleratedUser',
            ], true)) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            try {
                $returnType = $method->getReturnType();
                $isRelation = false;

                if ($returnType && (! $returnType->isBuiltin()) && is_subclass_of($returnType->getName(), Relation::class)) {
                    $isRelation = true;
                }

                if ($isRelation || $this->isLikelyRelation($method)) {
                    $result = $method->invoke($model);

                    if ($result instanceof Relation) {
                        $relatedClass = '\\'.get_class($result->getRelated());
                        $relationType = (new ReflectionClass($result))->getShortName();

                        $relationships[$method->getName()] = [
                            'type' => str_contains($relationType, 'Many') || str_contains($relationType, 'ToMany')
                                ? '\\'.Collection::class."|{$relatedClass}[]"
                                : "{$relatedClass}|null",
                        ];
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $relationships;
    }

    protected function isLikelyRelation(ReflectionMethod $method): bool
    {
        $doc = $method->getDocComment() ?: '';

        return str_contains($doc, '@return')
            && (str_contains($doc, 'Relation') || str_contains($doc, 'HasMany') || str_contains($doc, 'BelongsTo'));
    }

    protected function writeToModel(ReflectionClass $reflection, string $docBlock): void
    {
        $filePath = $reflection->getFileName();

        if ($filePath === false) {
            return;
        }

        $content = File::get($filePath);
        $className = $reflection->getShortName();
        $escapedClassName = preg_quote($className, '/');
        $pattern = "/(?P<doc>\/\*\*.*?\*\/\s*)?(?P<attributes>(?:#\[.*?\]\s+)*)class\s+{$escapedClassName}/s";

        if (preg_match($pattern, $content, $matches)) {
            $existingDoc = $matches['doc'] ?? '';
            $attributes = $matches['attributes'] ?? '';

            if (filled($existingDoc)) {
                $newContent = str_replace($existingDoc, $docBlock."\n", $content);
            } else {
                $replacement = "\n{$docBlock}\n{$attributes}class {$className}";
                $newContent = preg_replace($pattern, $replacement, $content, 1);
            }

            File::put($filePath, $newContent ?? $content);
        }
    }

    protected function qualifyModel(string $model): string
    {
        if (str_starts_with($model, 'App\\')) {
            return $model;
        }

        return 'App\\Models\\'.ltrim($model, '\\');
    }

    protected function searchForModel(): ?string
    {
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return null;
        }

        $models = collect(File::allFiles($modelPath))
            ->map(fn (SplFileInfo $file): string => str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
            ->sort()
            ->values()
            ->toArray();

        return search(
            label: 'Which model would you like to document?',
            options: fn (string $value): array => array_values(array_filter(
                $models,
                fn (string $model): bool => str_contains(strtolower($model), strtolower($value)),
            )),
        );
    }
}
