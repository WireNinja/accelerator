<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Agent;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use Throwable;
use WireNinja\Accelerator\Support\ModelContextScanner;

#[Signature('agent:model-context
    {model? : Model class, basename, or App\\Models-relative name}
    {--list : List application models without inspecting them}
    {--all : Inspect every application model}
    {--expand : Include schema, relation keys, events, observers, and model internals}
    {--database= : Database connection override}
    {--write= : Write JSON to this absolute or project-relative path}
    {--compact : Remove JSON indentation}')]
#[Description('Inspect application model, relation, cast, schema, and diagnostic context')]
class ModelContextCommand extends Command
{
    public function handle(ModelContextScanner $scanner): int
    {
        $model = $this->argument('model');
        $model = is_string($model) && $model !== '' ? $model : null;
        $all = (bool) $this->option('all');
        $list = (bool) $this->option('list');

        if (($model !== null && ($all || $list)) || ($all && $list)) {
            $this->components->error('Pass a model, --all, or --list; do not combine them.');

            return self::FAILURE;
        }

        try {
            $payload = ($list || ($model === null && ! $all))
                ? $scanner->registry()
                : $scanner->scan(
                    requestedModels: $all ? $scanner->availableModels() : [$model],
                    database: $this->stringOption('database'),
                    expand: (bool) $this->option('expand'),
                );
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        if (! $this->emit($payload)) {
            return self::FAILURE;
        }

        if (! isset($payload['errors'])) {
            return $payload['summary']['models_registered'] > 0 ? self::SUCCESS : self::FAILURE;
        }

        return $payload['summary']['models_scanned'] > 0 && $payload['summary']['errors'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload): bool
    {
        try {
            $json = json_encode(
                $payload,
                ($this->option('compact') ? 0 : JSON_PRETTY_PRINT)
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $jsonException) {
            $this->components->error($jsonException->getMessage());

            return false;
        }

        $outputPath = $this->stringOption('write');

        if ($outputPath === null) {
            $this->output->writeln($json);

            return true;
        }

        $resolvedPath = Str::startsWith($outputPath, DIRECTORY_SEPARATOR)
            ? $outputPath
            : base_path($outputPath);
        $directory = dirname($resolvedPath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::put($resolvedPath, $json.PHP_EOL) === false) {
            $this->components->error(sprintf('Unable to write model context to [%s].', $resolvedPath));

            return false;
        }

        $this->components->success(sprintf('Model context written to [%s].', $resolvedPath));

        return true;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
