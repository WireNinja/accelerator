<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Throwable;
use WireNinja\Accelerator\Installer\Installer;
use WireNinja\Accelerator\Installer\Onboarding;
use WireNinja\Accelerator\Installer\ProcessRunner;

final class InstallCommand extends Command
{
    /** @var list<string> */
    private const INSTALLER_OPTIONS = [
        'app-name', 'app-url', 'admin-name', 'admin-username', 'admin-email', 'admin-password',
        'package-manager', 'database', 'redis', 'features',
    ];

    protected $signature = 'accelerator:install
        {--app-name=}
        {--app-url=}
        {--admin-name=}
        {--admin-username=}
        {--admin-email=}
        {--admin-password=}
        {--package-manager=pnpm}
        {--database=sqlite}
        {--redis}
        {--features= : Comma-separated features; realtime also requires ACCELERATOR_REVERB_APP_ID/KEY/SECRET in non-interactive mode}
        {--force : Reinstall destructively without interactive confirmation}
        {--json : Emit one stable JSON result and suppress progress output}';

    protected $description = 'Destructively install Accelerator into a Laravel application';

    public function handle(): int
    {
        $projectRoot = base_path();

        try {
            $this->confirmReinstallation($projectRoot);
            $processRunner = new ProcessRunner(quiet: (bool) $this->option('json'));
            $plan = (new Onboarding($projectRoot))->plan($this->installerArguments());

            (new Installer(
                projectRoot: $projectRoot,
                packageRoot: dirname(__DIR__, 2),
                plan: $plan,
                processRunner: $processRunner,
                quiet: (bool) $this->option('json'),
            ))->run();

            if ($this->option('json')) {
                $this->writeJson('installed', true, [
                    'app_name' => $plan->appName,
                    'app_url' => $plan->appUrl,
                    'package_manager' => $plan->packageManager,
                    'database' => $plan->database,
                    'features' => $plan->features,
                ]);
            }
        } catch (Throwable $exception) {
            if ($this->option('json')) {
                $this->writeJson('error', false, error: $exception->getMessage());
            } else {
                $this->components->error($exception->getMessage());
                $this->components->warn('Fix the reported cause, then run the same command again to resume.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function installerArguments(): array
    {
        $arguments = $this->input->isInteractive() && ! $this->option('json') ? [] : ['--no-interaction'];

        foreach (self::INSTALLER_OPTIONS as $name) {
            $value = $this->option($name);

            if ($value === false || $value === null || $value === '') {
                continue;
            }

            $arguments[] = $value === true ? "--{$name}" : "--{$name}={$value}";
        }

        return $arguments;
    }

    private function confirmReinstallation(string $projectRoot): void
    {
        $path = $projectRoot.'/.accelerator/install-state.json';

        if (! is_file($path)) {
            return;
        }

        $journal = file_get_contents($path);

        if (! is_string($journal)) {
            throw new RuntimeException('Unable to read .accelerator/install-state.json.');
        }

        try {
            $state = json_decode($journal, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('.accelerator/install-state.json contains invalid JSON.', previous: $exception);
        }

        if (! is_array($state) || ! array_key_exists('finished', $state) || ! is_bool($state['finished'])) {
            throw new RuntimeException('.accelerator/install-state.json has an invalid structure.');
        }

        if ($state['finished'] === false) {
            return;
        }

        if (! $this->option('force')) {
            if (! $this->input->isInteractive() || $this->option('json')) {
                throw new RuntimeException('Accelerator is already installed. Reinstallation overwrites package-owned files and rebuilds the database; rerun with --force.');
            }

            $this->components->warn('Accelerator was already installed. Reinstallation overwrites package-owned files and runs migrate:fresh --seed.');

            if (! $this->confirm('Continue with destructive reinstallation?', false)) {
                throw new RuntimeException('Reinstallation cancelled.');
            }
        }

        if (! unlink($path)) {
            throw new RuntimeException('Unable to reset .accelerator/install-state.json for reinstallation.');
        }
    }

    /** @param array<string, mixed>|null $receipt @throws JsonException */
    private function writeJson(string $status, bool $changed, ?array $receipt = null, ?string $error = null): void
    {
        $this->output->writeln(json_encode([
            'schema' => 1,
            'ok' => $error === null,
            'command' => 'accelerator:install',
            'phase' => $error === null ? 'complete' : 'failed',
            'status' => $status,
            'changed' => $changed,
            'warnings' => [],
            'errors' => $error === null ? [] : [$error],
            'receipt' => $receipt,
            'next_commands' => $error === null
                ? ['php artisan accelerator:doctor --json --compact']
                : ['Fix the error and rerun the same command to resume.'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
