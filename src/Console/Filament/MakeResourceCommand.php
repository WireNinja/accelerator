<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Filament;

use BackedEnum;
use BladeUI\Icons\Factory as BladeIconFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use UnitEnum;

#[Signature('accelerator:make-resource
    {name : Eloquent model name}
    {--panel=admin : Filament panel ID}
    {--group= : NavigationGroup case, value, or label}
    {--icon= : Registered Blade icon name}
    {--label= : Singular model label}
    {--generate : Generate fields from the database}
    {--view : Generate a view page}
    {--soft-deletes : Add soft-delete support}
    {--model : Create the model}
    {--migration : Create a migration}
    {--factory : Create a factory}
    {--json : Emit machine-readable output}')]
#[Description('Generate and verify a Filament resource using Accelerator conventions.')]
final class MakeResourceCommand extends Command
{
    public function handle(Filesystem $files, BladeIconFactory $icons): int
    {
        $name = Str::studly((string) $this->argument('name'));
        $panel = (string) $this->option('panel');
        $icon = $this->stringOption('icon');
        $label = $this->stringOption('label');

        try {
            if ($icon !== null) {
                $icons->svg($icon);
            }

            $groupCase = $this->resolveNavigationGroup($this->stringOption('group'));
            $exitCode = $this->callSilently('make:filament-resource', array_filter([
                'model' => $name,
                '--panel' => $panel,
                '--generate' => (bool) $this->option('generate'),
                '--view' => (bool) $this->option('view'),
                '--soft-deletes' => (bool) $this->option('soft-deletes'),
                '--model' => (bool) $this->option('model'),
                '--migration' => (bool) $this->option('migration'),
                '--factory' => (bool) $this->option('factory'),
                '--no-interaction' => true,
            ], static fn (mixed $value): bool => $value !== false));

            if ($exitCode !== self::SUCCESS) {
                throw new RuntimeException('The native Filament resource generator failed.');
            }

            $resourcePath = $this->findResourcePath($files, $name, $panel);
            $this->applyMetadata($files, $resourcePath, $groupCase, $icon, $label);

            $shield = $this->runArtisan(['shield:safe-regenerate', '--panel='.$panel, '--json', '--compact']);
            $verify = $this->runArtisan(['accelerator:verify-resource', $name, '--compact']);
            $status = $shield->isSuccessful() && $verify->isSuccessful() ? 'PASS' : 'FAIL';

            return $this->emit([
                'status' => $status,
                'resource' => $name,
                'path' => Str::after($resourcePath, base_path().DIRECTORY_SEPARATOR),
                'shield_exit_code' => $shield->getExitCode(),
                'verification' => json_decode(trim($verify->getOutput()), true),
            ], $status === 'PASS' ? self::SUCCESS : self::FAILURE);
        } catch (Throwable $throwable) {
            return $this->emit([
                'status' => 'FAIL',
                'resource' => $name,
                'message' => $throwable->getMessage(),
            ], self::FAILURE);
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = trim((string) $this->option($name));

        return $value === '' ? null : $value;
    }

    private function resolveNavigationGroup(?string $requested): ?UnitEnum
    {
        if ($requested === null) {
            return null;
        }

        $enum = config('accelerator.enums.navigation_group');

        if (! is_string($enum) || ! enum_exists($enum)) {
            throw new RuntimeException('Configure accelerator.enums.navigation_group before using --group.');
        }

        foreach ($enum::cases() as $case) {
            $value = $case instanceof BackedEnum ? (string) $case->value : $case->name;
            $label = method_exists($case, 'getLabel') ? (string) $case->getLabel() : $value;

            if (in_array(Str::lower($requested), [Str::lower($case->name), Str::lower($value), Str::lower($label)], true)) {
                return $case;
            }
        }

        throw new RuntimeException("Unknown navigation group [{$requested}]. Add it to the app NavigationGroup enum first.");
    }

    private function findResourcePath(Filesystem $files, string $name, string $panel): string
    {
        $panelDirectory = $panel === 'admin' ? '' : Str::studly($panel).DIRECTORY_SEPARATOR;
        $root = app_path('Filament'.DIRECTORY_SEPARATOR.$panelDirectory.'Resources');
        $expected = Str::pluralStudly($name).'Resource.php';
        $matches = array_values(array_filter(
            $files->allFiles($root),
            static fn (\SplFileInfo $file): bool => $file->getFilename() === $expected,
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException("Unable to uniquely locate generated resource [{$expected}].");
        }

        return $matches[0]->getPathname();
    }

    private function applyMetadata(Filesystem $files, string $path, ?UnitEnum $group, ?string $icon, ?string $label): void
    {
        if ($group === null && $icon === null && $label === null) {
            return;
        }

        $contents = $files->get($path);
        $properties = [];

        if ($icon !== null) {
            $properties[] = "    protected static string|\\BackedEnum|null \$navigationIcon = '{$icon}';";
        }

        if ($label !== null) {
            $properties[] = "    protected static ?string \$modelLabel = '".addslashes($label)."';";
        }

        if ($group !== null) {
            $properties[] = sprintf(
                '    protected static string|\\UnitEnum|null $navigationGroup = \\%s::%s;',
                $group::class,
                $group->name,
            );
        }

        $updated = preg_replace(
            '/(class\s+\w+Resource\s+extends\s+Resource\s*\{)/',
            "$1\n".implode("\n\n", $properties),
            $contents,
            1,
            $count,
        );

        if (! is_string($updated) || $count !== 1) {
            throw new RuntimeException("Unable to apply Accelerator metadata to [{$path}].");
        }

        $files->put($path, $updated);
    }

    /** @param list<string> $arguments */
    private function runArtisan(array $arguments): Process
    {
        $process = new Process([PHP_BINARY, 'artisan', ...$arguments], base_path());
        $process->setTimeout(300);
        $process->run();

        return $process;
    }

    /** @param array<string, mixed> $payload */
    private function emit(array $payload, int $exitCode): int
    {
        if (! $this->option('json')) {
            $message = $payload['message'] ?? "Resource [{$payload['resource']}] {$payload['status']}";
            $exitCode === self::SUCCESS
                ? $this->components->success((string) $message)
                : $this->components->error((string) $message);

            return $exitCode;
        }

        try {
            $this->output->writeln(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->output->writeln('{"status":"FAIL","message":"JSON encoding failed."}');

            return self::FAILURE;
        }

        return $exitCode;
    }
}
