<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use JsonException;
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
        {--json : Emit one stable JSON result and suppress progress output}';

    protected $description = 'Install Accelerator into a pristine Laravel application';

    public function handle(): int
    {
        $projectRoot = base_path();

        if ($this->alreadyInstalled($projectRoot)) {
            if ($this->option('json')) {
                $this->writeJson('already_installed', false);
            } else {
                $this->components->info('Accelerator is already installed. Nothing changed.');
            }

            return self::SUCCESS;
        }

        try {
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

    private function alreadyInstalled(string $projectRoot): bool
    {
        $journal = @file_get_contents($projectRoot.'/.accelerator/install-state.json');
        $state = is_string($journal) ? json_decode($journal, true) : null;

        if (is_array($state)) {
            return ($state['finished'] ?? false) === true;
        }

        $environment = @file_get_contents($projectRoot.'/.env');

        return is_string($environment) && str_contains($environment, 'ACCELERATOR_UPLOAD_MAX_MB=');
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
