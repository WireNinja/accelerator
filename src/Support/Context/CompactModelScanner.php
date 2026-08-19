<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Context;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use SplFileInfo;
use Throwable;

final class CompactModelScanner
{
    /** @return list<class-string<Model>> */
    public function all(): array
    {
        $classes = [];

        foreach ($this->phpFiles(app_path('Models')) as $file) {
            $class = $this->classFromFile($file);

            if ($class !== null && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /** @return class-string<Model> */
    public function resolve(string $requested): string
    {
        $requested = ltrim(trim($requested), '\\');

        if (class_exists($requested) && is_subclass_of($requested, Model::class)) {
            return $requested;
        }

        $matches = array_values(array_filter(
            $this->all(),
            static fn (string $class): bool => strcasecmp(class_basename($class), $requested) === 0
                || strcasecmp($class, 'App\\Models\\'.str_replace('/', '\\', $requested)) === 0,
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException(count($matches) === 0
                ? "Application model [{$requested}] was not found."
                : "Model [{$requested}] is ambiguous: ".implode(', ', $matches));
        }

        return $matches[0];
    }

    /** @return array<string, mixed> */
    public function scan(string $requested, bool $expand = false): array
    {
        $class = $this->resolve($requested);
        $model = new $class;
        $reflection = new ReflectionClass($class);
        $policy = Gate::getPolicyFor($class);
        $relations = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfRequiredParameters() !== 0 || $method->isStatic() || $method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $type = $method->getReturnType();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), Relation::class, true)) {
                $relations[] = ['name' => $method->getName(), 'type' => class_basename($type->getName())];
            }
        }

        $payload = [
            'schema' => 1,
            'type' => 'model',
            'class' => $class,
            'file' => $this->relative((string) $reflection->getFileName()),
            'table' => $model->getTable(),
            'key' => $model->getKeyName(),
            'casts' => $model->getCasts(),
            'relations' => $relations,
            'files' => array_filter([
                'factory' => $this->existing('database/factories/'.class_basename($class).'Factory.php'),
                'policy' => is_object($policy) ? $this->relative((string) (new ReflectionClass($policy))->getFileName()) : null,
            ]),
            'issues' => [],
        ];

        if ($expand) {
            $payload['expand'] = $this->expandedDatabase($model);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function expandedDatabase(Model $model): array
    {
        try {
            return [
                'connection' => $model->getConnectionName() ?: config('database.default'),
                'columns' => Schema::connection($model->getConnectionName())->getColumns($model->getTable()),
                'indexes' => Schema::connection($model->getConnectionName())->getIndexes($model->getTable()),
                'events' => $model->getObservableEvents(),
            ];
        } catch (Throwable $throwable) {
            return ['error' => $throwable->getMessage()];
        }
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $files = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @return class-string|null */
    private function classFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        if (! is_string($contents)
            || preg_match('/namespace\s+([^;]+);/', $contents, $namespace) !== 1
            || preg_match('/(?:final\s+|abstract\s+)?class\s+(\w+)/', $contents, $class) !== 1) {
            return null;
        }

        $name = trim($namespace[1]).'\\'.$class[1];

        return class_exists($name) ? $name : null;
    }

    private function existing(string $relative): ?string
    {
        return is_file(base_path($relative)) ? $relative : null;
    }

    private function relative(string $path): string
    {
        return str_starts_with($path, base_path().DIRECTORY_SEPARATOR)
            ? substr($path, strlen(base_path()) + 1)
            : $path;
    }
}
