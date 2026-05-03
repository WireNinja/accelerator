<?php

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use Throwable;
use WireNinja\Accelerator\Support\ModelContextScanner;

#[Signature('accelerator:model-context
    {model? : Optional model class name. Omit to scan every model in app/Models}
    {--database= : Database connection override for the scan}
    {--write= : Write the JSON payload to the given path instead of stdout}
    {--counts : Include table row counts in the database summary}
    {--views : Include database views in the summary}
    {--types : Include database user-defined types in the summary}
    {--compact : Output compact JSON instead of pretty JSON}')]
#[Description('Scan model, relation, cast, and schema context into one JSON payload')]
class ModelContextCommand extends Command
{
    public function handle(ModelContextScanner $scanner): int
    {
        try {
            $payload = $scanner->scan(
                requestedModels: $this->argument('model') ? [$this->argument('model')] : [],
                database: $this->option('database'),
                includeCounts: (bool) $this->option('counts'),
                includeViews: (bool) $this->option('views'),
                includeTypes: (bool) $this->option('types'),
            );
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return 1;
        }

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

            return 1;
        }

        $outputPath = $this->option('write');

        if (is_string($outputPath) && $outputPath !== '') {
            $resolvedPath = $this->resolveOutputPath($outputPath);
            $directory = dirname($resolvedPath);

            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($resolvedPath, $json.PHP_EOL);

            $this->components->success(sprintf('Model context written to [%s].', $resolvedPath));

            if ($payload['summary']['errors'] > 0) {
                $this->components->warn(sprintf('Scan completed with %s error(s). Check the JSON payload for details.', $payload['summary']['errors']));
            }
        } else {
            $this->output->writeln($json);
        }

        return $payload['summary']['models_scanned'] > 0 ? 0 : 1;
    }

    protected function resolveOutputPath(string $path): string
    {
        if (Str::startsWith($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
