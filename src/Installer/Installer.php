<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use JsonException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;

final readonly class Installer
{
    public function __construct(
        private string $projectRoot,
        private string $packageRoot,
        private InstallPlan $plan,
        private ProcessRunner $processRunner,
        private bool $quiet = false,
    ) {}

    /** @throws JsonException */
    public function run(): void
    {
        $context = new InstallContext(
            projectRoot: $this->projectRoot,
            packageRoot: $this->packageRoot,
            plan: $this->plan,
            processRunner: $this->processRunner,
        );
        $scaffolder = new Scaffolder($context);
        $preflight = new Preflight($context, $scaffolder);
        $environment = new EnvironmentWriter($context);
        $dependencies = new DependencyInstaller($context);
        $application = new ApplicationFinalizer($context);

        $preflight->runtime();
        $journal = new InstallJournal($this->projectRoot, $this->plan);

        if ($journal->isFinished()) {
            $this->outro('Accelerator v2 is already installed with this configuration. Nothing changed.');

            return;
        }

        if (! $journal->isCompleted('scaffold')) {
            $preflight->recipeTargets();
        }

        if (! $journal->isCompleted('environment')) {
            $preflight->environmentTargets();
        }

        $journal->start();
        $this->step($journal, 'scaffold', $scaffolder->install(...));
        $this->step($journal, 'environment', $environment->write(...));
        $this->step($journal, 'composer', $dependencies->composer(...));
        $this->step($journal, 'frontend', $dependencies->frontend(...));
        $this->step($journal, 'application', function () use ($application, $journal): void {
            $application->finalize($journal);
        });
        $this->step($journal, 'quality', $application->quality(...));
        $journal->finish();

        if ($this->plan->deploy) {
            $firstStage = $this->plan->deploymentMode === 'dual' ? 'staging' : 'production';
            $this->outro("Accelerator v2 installed. Complete .accelerator/environments/{$firstStage}.env, then run php artisan accelerator:configure environment --stage={$firstStage}.");

            return;
        }

        $this->outro('Accelerator v2 installed. Run php artisan accelerator:doctor at any time to verify it.');
    }

    /**
     * @param  callable(): void  $callback
     *
     * @throws JsonException
     */
    private function step(InstallJournal $journal, string $name, callable $callback): void
    {
        if ($journal->isCompleted($name)) {
            $this->info("Skipping completed step: {$name}");

            return;
        }

        $this->info("Installing: {$name}");
        $callback();
        $journal->complete($name);
    }

    private function info(string $message): void
    {
        if (! $this->quiet) {
            info($message);
        }
    }

    private function outro(string $message): void
    {
        if (! $this->quiet) {
            outro($message);
        }
    }
}
