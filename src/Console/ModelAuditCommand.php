<?php

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Throwable;
use WireNinja\Accelerator\Console\Concerns\HasBanner;

use function Laravel\Prompts\search;

#[Signature('accelerator:model-audit {model? : The model class name (e.g., User or App\Models\User)}')]
#[Description('Analyze model for best practices: BigDecimal compliance, immutable dates, and PHPDoc drift')]
class ModelAuditCommand extends Command
{
    use HasBanner;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayBanner();

        $modelInput = $this->argument('model');

        if (! $modelInput) {
            $modelInput = $this->searchForModel();
        }

        if (! $modelInput) {
            return 0;
        }

        $modelClass = $this->qualifyModel($modelInput);

        if (! class_exists($modelClass)) {
            $this->components->error("Model class [{$modelClass}] not found.");

            return 1;
        }

        try {
            /** @var Model $modelInstance */
            $modelInstance = new $modelClass;
            $reflection = new ReflectionClass($modelClass);

            $this->components->info("Auditing Model Architecture: <fg=cyan>{$modelClass}</>");
            $this->newLine();

            $auditResults = $this->performanceAudit($modelInstance, $reflection);

            $this->displayAuditReport($auditResults);
        } catch (Throwable $e) {
            $this->components->error("Audit failed: {$e->getMessage()}");

            return 1;
        }

        return empty($auditResults['findings']) ? 0 : 1;
    }

    /**
     * Perform deep analysis on the model.
     */
    protected function performanceAudit(Model $model, ReflectionClass $reflection): array
    {
        $findings = [];
        $table = $model->getTable();
        $casts = $model->getCasts();
        $docComment = $reflection->getDocComment() ?: '';
        $fileContent = File::get($reflection->getFileName());

        // 1. Column Integrity & Cast Audit
        try {
            $columns = Schema::getColumnListing($table);
            foreach ($columns as $column) {
                $type = Schema::getColumnType($table, $column);

                // Financial Precision Check
                if (in_array($type, ['decimal', 'numeric', 'float', 'double'])) {
                    $cast = $casts[$column] ?? null;
                    if (! $cast || ! str_contains($cast, 'BigDecimalCast')) {
                        $findings[] = [
                            'target' => $column,
                            'issue' => "Financial column [{$type}] missing BigDecimalCast integration.",
                            'severity' => 'critical',
                            'suggestion' => "Add '{$column}' => \WireNinja\Accelerator\Database\Casts\BigDecimalCast::class to \$casts",
                        ];
                    }
                }

                // Temporary Safety Check (Immutability)
                if (in_array($type, ['datetime', 'timestamp', 'date'])) {
                    $cast = $casts[$column] ?? null;
                    $isImmutable = $cast && (str_contains($cast, 'immutable') || str_contains($cast, 'CarbonImmutable'));
                    if (! $isImmutable) {
                        $findings[] = [
                            'target' => $column,
                            'issue' => 'Mutable date detected. Premium models should use CarbonImmutable.',
                            'severity' => 'warning',
                            'suggestion' => "Change cast to 'immutable_datetime' or use CarbonImmutable::class",
                        ];
                    }
                }

                // Documentation Drift Check
                if (! str_contains($docComment, '$'.$column)) {
                    $findings[] = [
                        'target' => $column,
                        'issue' => 'Column missing from PHPDoc @property block.',
                        'severity' => 'info',
                        'suggestion' => "Run 'php artisan accelerator:model-doc {$reflection->getShortName()} --write'",
                    ];
                }
            }
        } catch (Throwable $e) {
            $findings[] = ['target' => 'Schema', 'issue' => "Database inspection failed: {$e->getMessage()}", 'severity' => 'critical', 'suggestion' => 'Check database connection'];
        }

        // 2. Relationship Documentation Audit
        $relationships = $this->getRelationshipMethods($model, $reflection);
        foreach ($relationships as $relName) {
            if (! str_contains($docComment, '$'.$relName)) {
                $findings[] = [
                    'target' => "{$relName}()",
                    'issue' => 'Relationship missing from PHPDoc properties.',
                    'severity' => 'info',
                    'suggestion' => 'Update PHPDoc to include the related model and collection type.',
                ];
            }
        }

        // 3. Code Quality Signals
        if (! str_contains($fileContent, 'declare(strict_types=1);')) {
            $findings[] = [
                'target' => 'File',
                'issue' => 'Missing strict_types declaration.',
                'severity' => 'warning',
                'suggestion' => 'Add declare(strict_types=1); to the top of the file.',
            ];
        }

        return [
            'findings' => $findings,
            'summary' => [
                'columns' => count($columns ?? []),
                'relationships' => count($relationships),
            ],
        ];
    }

    /**
     * Map relationship methods.
     */
    protected function getRelationshipMethods(Model $model, ReflectionClass $reflection): array
    {
        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (in_array($method->getDeclaringClass()->getName(), [
                Model::class,
                'Illuminate\Database\Eloquent\Model',
                'Illuminate\Foundation\Auth\User',
                'WireNinja\Accelerator\Model\AcceleratedUser',
            ])) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            try {
                $result = $method->invoke($model);
                if ($result instanceof Relation) {
                    $methods[] = $method->getName();
                }
            } catch (Throwable) {
                // Skip methods that can't be safely invoked
            }
        }

        return $methods;
    }

    /**
     * Display the audit report.
     */
    protected function displayAuditReport(array $results): void
    {
        if (empty($results['findings'])) {
            $this->components->success('AUDIT PASSED: Model adheres to premium Accelerator standards.');

            return;
        }

        $this->components->warn('Architectural issues detected:');
        $this->newLine();

        $rows = [];
        foreach ($results['findings'] as $finding) {
            $severity = match ($finding['severity']) {
                'critical' => '<fg=red>CRITICAL</>',
                'warning' => '<fg=yellow>WARNING</>',
                default => '<fg=gray>INFO</>'
            };

            $rows[] = [
                $finding['target'],
                $severity,
                $finding['issue'],
                $finding['suggestion'],
            ];
        }

        $this->table(['Target', 'Severity', 'Issue', 'Recommendation'], $rows);
        $this->newLine();

        $this->components->info("Audit completed: Checked {$results['summary']['columns']} columns and {$results['summary']['relationships']} relationships.");
    }

    /**
     * Search for a model.
     */
    protected function searchForModel(): ?string
    {
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return null;
        }

        $models = collect(File::allFiles($modelPath))
            ->map(fn ($file) => str_replace('.php', '', $file->getFilename()))
            ->toArray();

        return search(
            label: 'Which model would you like to audit?',
            options: fn (string $value) => array_filter(
                $models,
                fn (string $model) => str_contains(strtolower($model), strtolower($value))
            )
        );
    }

    /**
     * Qualify the model name.
     */
    protected function qualifyModel(string $model): string
    {
        return str_contains($model, '\\') ? $model : "App\\Models\\{$model}";
    }
}
