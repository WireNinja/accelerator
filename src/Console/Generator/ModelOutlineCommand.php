<?php

namespace WireNinja\Accelerator\Console\Generator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;
use WireNinja\Accelerator\Console\Concerns\HasBanner;
use WireNinja\Accelerator\Model\AcceleratedUser;

use function Laravel\Prompts\search;

#[Signature('accelerator:generate-model-outline {model? : The model class name (e.g., User or App\Models\User)} {--write : Write the output to storage/app/private/model-outlines/ folder} {--merge : Merge all outputs into a single file when used with --write-all-quiet} {--write-all-quiet : Write output for all models to storage/app/private/model-outlines/ without console printing}')]
#[Description('Outline a model: columns, relationships, casts, and hidden attributes')]
class ModelOutlineCommand extends Command
{
    use HasBanner;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isQuiet = $this->option('write-all-quiet');

        if (! $isQuiet) {
            $this->displayBanner();
        }

        if ($isQuiet) {
            return $this->handleAllModels(true);
        }

        if ($this->option('merge') && ! $this->argument('model')) {
            return $this->handleAllModels(false);
        }

        $modelInput = $this->argument('model');

        if (! $modelInput) {
            $modelInput = $this->searchForModel();
        }

        if (! $modelInput) {
            return 0;
        }

        return $this->outlineModel($modelInput);
    }

    /**
     * Handle outlining a single model.
     */
    protected function outlineModel(string $modelInput): int
    {
        $modelClass = $this->qualifyModel($modelInput);

        if (! class_exists($modelClass)) {
            $this->components->error(sprintf('Model class [%s] not found.', $modelClass));

            return 1;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            $this->components->error(sprintf('Class [%s] is not a Laravel Model.', $modelClass));

            return 1;
        }

        $modelInstance = new $modelClass;
        $reflection = new ReflectionClass($modelClass);

        if ($this->option('write')) {
            $content = $this->captureOutline($modelClass, $modelInstance, $reflection);
            $this->saveOutline($modelClass, $content);
            $this->components->success(sprintf('Outline for [%s] written to storage/app/private/model-outlines/.', $modelClass));

            return 0;
        }

        $this->components->info(sprintf('Outlining Model: <fg=cyan>%s</>', $modelClass));
        $this->newLine();

        $this->displayGeneralInfo($modelInstance);
        $this->displayColumns($modelInstance);
        $this->displayRelationships($modelInstance, $reflection);
        $this->displayCasts($modelInstance);
        $this->displayHidden($modelInstance);

        return 0;
    }

    /**
     * Handle all models in the application.
     */
    protected function handleAllModels(bool $quiet = false): int
    {
        $models = $this->getAllModels();
        $mergedContent = '';

        foreach ($models as $modelName) {
            $modelClass = 'App\Models\\'.$modelName;

            if (! class_exists($modelClass)) {
                continue;
            }

            $reflection = new ReflectionClass($modelClass);
            if ($reflection->isAbstract()) {
                continue;
            }

            /** @var Model $modelInstance */
            $modelInstance = new $modelClass;

            $content = $this->captureOutline($modelClass, $modelInstance, $reflection);

            if ($this->option('merge')) {
                $mergedContent .= $content."\n\n".str_repeat('=', 80)."\n\n";
            } else {
                $this->saveOutline($modelClass, $content);
            }

            if (! $quiet) {
                $this->components->task(sprintf('Processed [%s]', $modelName));
            }
        }

        if ($this->option('merge')) {
            $this->saveOutline('all_models', $mergedContent);
        }

        if (! $quiet) {
            $this->components->success('Models have been outlined to storage/app/private/model-outlines/.');
        }

        return 0;
    }

    /**
     * Capture the model outline into a string.
     */
    protected function captureOutline(string $modelClass, Model $model, ReflectionClass $reflection): string
    {
        $bufferedOutput = new BufferedOutput;
        $originalOutput = $this->output;
        $originalComponents = $this->components;

        // Temporarily swap the output
        $newOutput = new OutputStyle($this->input, $bufferedOutput);
        $this->output = $newOutput;

        // Re-initialize components to use the buffered output
        $this->components = new Factory($newOutput);

        $this->displayGeneralInfo($model);
        $this->displayColumns($model);
        $this->displayRelationships($model, $reflection);
        $this->displayCasts($model);
        $this->displayHidden($model);

        $content = $bufferedOutput->fetch();

        // Restore original output and components
        $this->output = $originalOutput;
        $this->components = $originalComponents;

        // Build the final clean output with a header
        $header = sprintf('Model: %s%s', $modelClass, PHP_EOL);
        $header .= 'Table: '.$model->getTable()."\n";
        $header .= str_repeat('-', 50)."\n\n";

        // Strip ANSI escape codes
        $cleanContent = preg_replace('#\x1b[[][^A-Za-z]*[A-Za-z]#', '', $content);

        return $header.$cleanContent;
    }

    /**
     * Write the captured outline to a file.
     */
    protected function saveOutline(string $name, string $content): void
    {
        $dir = storage_path('app/private/model-outlines');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $filename = (str_contains($name, '\\') ? (new ReflectionClass($name))->getShortName() : $name).'.txt';
        $path = sprintf('%s/%s', $dir, $filename);

        File::put($path, $content);
    }

    /**
     * Search for a model in the application.
     */
    protected function searchForModel(): ?string
    {
        $models = $this->getAllModels();

        if ($models === []) {
            $this->components->error('No models found in app/Models.');

            return null;
        }

        return search(
            label: 'Which model would you like to outline?',
            options: fn (string $value): array => array_filter(
                $models,
                fn (string $model): bool => str_contains(strtolower($model), strtolower($value))
            ),
            placeholder: 'Search for a model...'
        );
    }

    /**
     * Get all models in the app/Models directory.
     */
    protected function getAllModels(): array
    {
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return [];
        }

        return collect(File::allFiles($modelPath))
            ->map(fn ($file): string => str_replace('.php', '', $file->getFilename()))
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Qualify the model name with the correct namespace.
     */
    protected function qualifyModel(string $model): string
    {
        if (str_contains($model, '\\')) {
            return $model;
        }

        return 'App\Models\\'.$model;
    }

    /**
     * Display general model information.
     */
    protected function displayGeneralInfo(Model $model): void
    {
        $this->components->twoColumnDetail('Table', sprintf('<fg=yellow>%s</>', $model->getTable()));
        $this->components->twoColumnDetail('Connection', $model->getConnectionName() ?: config('database.default'));
        $this->components->twoColumnDetail('Primary Key', $model->getKeyName());
        $this->components->twoColumnDetail('Incrementing', $model->getIncrementing() ? 'Yes' : 'No');
        $this->components->twoColumnDetail('Timestamps', $model->usesTimestamps() ? 'Yes' : 'No');
        $this->newLine();
    }

    /**
     * Display the model's columns.
     */
    protected function displayColumns(Model $model): void
    {
        $this->components->info('Columns');

        $table = $model->getTable();
        try {
            $columns = Schema::getColumnListing($table);
        } catch (Throwable) {
            $columns = [];
        }

        if (empty($columns)) {
            $this->line('  <fg=gray>No columns found (check database connection).</>');
            $this->newLine();

            return;
        }

        $rows = [];
        foreach ($columns as $column) {
            try {
                $type = Schema::getColumnType($table, $column);
            } catch (Throwable) {
                $type = 'unknown';
            }

            $rows[] = [$column, $type];
        }

        $this->table(['Name', 'Type'], $rows);
        $this->newLine();
    }

    /**
     * Display the model's relationships using reflection.
     */
    protected function displayRelationships(Model $model, ReflectionClass $reflection): void
    {
        $this->components->info('Relationships');

        $relationships = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip methods from base classes
            if (in_array(
                $method->getDeclaringClass()->getName(),
                [
                    Model::class,
                    Model::class,
                    User::class,
                    AcceleratedUser::class,
                ],
                true
            )) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            try {
                // Try to get relationship type from return type hint first for speed
                $returnType = $method->getReturnType();
                $relationshipType = null;
                $relatedModel = null;

                if ($returnType && ! $returnType->isBuiltin()) {
                    $className = $returnType->getName();
                    if (is_subclass_of($className, Relation::class)) {
                        $result = $method->invoke($model);
                        $relationshipType = (new ReflectionClass($result))->getShortName();
                        $relatedModel = (new ReflectionClass($result->getRelated()))->getName();
                    }
                }

                if (! $relationshipType) {
                    // Fallback: Invoke and check result
                    $result = $method->invoke($model);

                    if ($result instanceof Relation) {
                        $relationshipType = (new ReflectionClass($result))->getShortName();
                        $relatedModel = (new ReflectionClass($result->getRelated()))->getName();
                    }
                }

                if ($relationshipType) {
                    $relationships[] = [
                        $method->getName(),
                        $relationshipType,
                        $relatedModel,
                    ];
                }
            } catch (Throwable) {
                // Ignore failures
            }
        }

        if ($relationships === []) {
            $this->line('  <fg=gray>No relationships found.</>');
        } else {
            $this->table(['Method', 'Type', 'Related Model'], $relationships);
        }

        $this->newLine();
    }

    /**
     * Display the model's attribute casts.
     */
    protected function displayCasts(Model $model): void
    {
        $this->components->info('Casts');
        $casts = $model->getCasts();

        if (empty($casts)) {
            $this->line('  <fg=gray>No casts defined.</>');
        } else {
            $rows = [];
            foreach ($casts as $column => $cast) {
                $rows[] = [$column, $cast];
            }

            $this->table(['Column', 'Cast Type'], $rows);
        }

        $this->newLine();
    }

    /**
     * Display the model's hidden attributes.
     */
    protected function displayHidden(Model $model): void
    {
        $this->components->info('Hidden Attributes');
        $hidden = $model->getHidden();

        if (empty($hidden)) {
            $this->line('  <fg=gray>No hidden attributes.</>');
        } else {
            foreach ($hidden as $attr) {
                $this->line(sprintf('  - <fg=red>%s</>', $attr));
            }
        }

        $this->newLine();
    }
}
