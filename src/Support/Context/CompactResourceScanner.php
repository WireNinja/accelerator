<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Context;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final readonly class CompactResourceScanner
{
    public function __construct(private ResourceRegistry $registry) {}

    /** @return array<string, mixed> */
    public function scan(string $requested, bool $expand = false): array
    {
        $class = $this->registry->resolve($requested);

        if (! is_subclass_of($class, Resource::class)) {
            throw new RuntimeException("Registered class [{$class}] is not a Filament resource.");
        }

        $reflection = new ReflectionClass($class);
        $model = $class::getModel();
        $policy = is_subclass_of($model, Model::class) ? Gate::getPolicyFor($model) : null;
        $files = $this->relatedFiles((string) $reflection->getFileName());
        $pages = array_keys($class::getPages());
        $actions = $this->sourceActions(array_values($files));
        $panels = $this->registry->all()[$class] ?? [];
        $issues = [];

        if ($panels === []) {
            $issues[] = 'Resource is not registered in any Filament panel.';
        }

        if (! is_subclass_of($model, Model::class)) {
            $issues[] = 'Resource model is not an Eloquent model.';
        }

        if ($policy === null) {
            $issues[] = 'Resource model has no registered policy.';
        }

        $payload = [
            'schema' => 1,
            'type' => 'resource',
            'class' => $class,
            'model' => $model,
            'files' => $files,
            'pages' => $pages,
            'actions' => $actions,
            'checks' => [
                'panels' => $panels,
                'policy' => is_object($policy) ? $policy::class : null,
            ],
            'issues' => $issues,
        ];

        if ($expand) {
            $payload['expand'] = [
                'methods' => array_values(array_map(
                    static fn (ReflectionMethod $method): string => $method->getName(),
                    array_filter(
                        $reflection->getMethods(),
                        static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class,
                    ),
                )),
                'source_lines' => [$reflection->getStartLine(), $reflection->getEndLine()],
            ];
        }

        return $payload;
    }

    /** @return array<string, string> */
    private function relatedFiles(string $resourceFile): array
    {
        $directory = dirname($resourceFile);
        $files = ['resource' => $this->relative($resourceFile)];

        foreach (['Schemas' => 'form', 'Tables' => 'table'] as $folder => $key) {
            $matches = glob($directory.'/'.$folder.'/*.php') ?: [];

            if (count($matches) === 1) {
                $files[$key] = $this->relative($matches[0]);
            }
        }

        return $files;
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    private function sourceActions(array $files): array
    {
        $actions = [];

        foreach ($files as $file) {
            $path = str_starts_with($file, '/') ? $file : base_path($file);
            $contents = is_file($path) ? file_get_contents($path) : false;

            if (is_string($contents)) {
                preg_match_all('/\b([A-Z][A-Za-z0-9]+Action)::/', $contents, $matches);
                $actions = [...$actions, ...$matches[1]];
            }
        }

        $actions = array_values(array_unique($actions));
        sort($actions);

        return $actions;
    }

    private function relative(string $path): string
    {
        return str_starts_with($path, base_path().DIRECTORY_SEPARATOR)
            ? substr($path, strlen(base_path()) + 1)
            : $path;
    }
}
