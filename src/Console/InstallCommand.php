<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use Throwable;
use WireNinja\Accelerator\Installer\Installer;
use WireNinja\Accelerator\Installer\Onboarding;
use WireNinja\Accelerator\Installer\ProcessRunner;

final class InstallCommand extends Command
{
    /** @var list<string> */
    private const INSTALLER_OPTIONS = [
        'app-name', 'app-url', 'admin-name', 'admin-username', 'admin-email', 'admin-password',
        'package-manager', 'database', 'redis', 'features', 'deploy', 'deployment-mode', 'project',
        'ssh-host', 'repo', 'branch', 'domain', 'deploy-root', 'staging-domain',
        'staging-deploy-root', 'http-runtime',
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
        {--features=}
        {--deploy}
        {--deployment-mode=single}
        {--project=}
        {--ssh-host=}
        {--repo=}
        {--branch=main}
        {--domain=}
        {--deploy-root=}
        {--staging-domain=}
        {--staging-deploy-root=}
        {--http-runtime=octane}';

    protected $description = 'Install Accelerator into a pristine Laravel application';

    public function handle(): int
    {
        $projectRoot = base_path();

        if ($this->alreadyInstalled($projectRoot)) {
            $this->components->info('Accelerator is already installed. Nothing changed.');

            return self::SUCCESS;
        }

        try {
            $processRunner = new ProcessRunner;
            $plan = (new Onboarding($projectRoot, $processRunner))->plan($this->installerArguments());

            (new Installer(
                projectRoot: $projectRoot,
                packageRoot: dirname(__DIR__, 2),
                plan: $plan,
                processRunner: $processRunner,
            ))->run();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());
            $this->components->warn('Fix the reported cause, then run the same command again to resume.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function installerArguments(): array
    {
        $arguments = $this->input->isInteractive() ? [] : ['--no-interaction'];

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
}
