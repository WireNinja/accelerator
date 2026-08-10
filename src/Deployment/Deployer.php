<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final readonly class Deployer
{
    public function __construct(private string $projectRoot) {}

    /**
     * @param  list<string>  $options
     * @param  array<string, string>  $environment
     */
    public function run(string $task, string $stage, array $options = [], bool $capture = false, array $environment = []): string
    {
        $binary = $this->projectRoot.'/vendor/bin/dep';

        if (! is_file($binary)) {
            throw new RuntimeException('Deployer is missing. Run composer install or reinstall Accelerator dev dependencies.');
        }

        $recipe = dirname(__DIR__, 2).'/resources/deployer/deploy.php';
        $process = new Process([$binary, '--file='.$recipe, $task, $stage, ...$options], $this->projectRoot, [
            'ACCELERATOR_PROJECT_ROOT' => $this->projectRoot,
            'ACCELERATOR_DEPLOY_STAGE' => $stage,
            ...$environment,
        ]);
        $process->setTimeout(null);

        if ($capture) {
            try {
                $process->mustRun();
            } catch (Throwable $exception) {
                throw new RuntimeException("Deployer task [{$task}] failed.", previous: $exception);
            }

            return trim($process->getOutput());
        }

        $process->run(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Deployer task [{$task}] failed with exit code {$process->getExitCode()}.");
        }

        return '';
    }
}
