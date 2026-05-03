<?php

namespace WireNinja\Accelerator\Console\Agent;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use Throwable;
use WireNinja\Accelerator\Support\Filament\ResourceContextScanner;

#[Signature('agent:resource-context
    {resource? : Optional resource key/class or linked page, form, table, or relation manager class}
    {--list : List discovered resource registry only}
    {--registry : Include registry data together with the resource payload}
    {--expand : Ignore #[DiscoverShouldMinify] and return expanded payloads}
    {--write= : Write the JSON payload to the given path instead of stdout}
    {--compact : Output compact JSON instead of pretty JSON}')]
#[Description('Scan one Filament resource into a single AI-friendly JSON payload')]
class ResourceContextCommand extends Command
{
    public function handle(ResourceContextScanner $scanner): int
    {
        try {
            $payload = $scanner->scan(
                resource: $this->option('list') ? null : $this->argument('resource'),
                includeRegistry: (bool) $this->option('registry') || (bool) $this->option('list'),
                expandMinified: (bool) $this->option('expand'),
            );
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
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

            return self::FAILURE;
        }

        $outputPath = $this->option('write');

        if (is_string($outputPath) && $outputPath !== '') {
            $resolvedPath = $this->resolveOutputPath($outputPath);
            $directory = dirname($resolvedPath);

            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($resolvedPath, $json.PHP_EOL);

            $this->components->success(sprintf('Resource context written to [%s].', $resolvedPath));
        } else {
            $this->output->writeln($json);
        }

        return isset($payload['resource']) || isset($payload['registry'])
            ? self::SUCCESS
            : self::FAILURE;
    }

    protected function resolveOutputPath(string $path): string
    {
        if (Str::startsWith($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
