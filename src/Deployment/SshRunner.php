<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use RuntimeException;
use Symfony\Component\Process\Process;

final readonly class SshRunner
{
    public function __construct(private string $projectRoot) {}

    public function run(string $host, string $command, ?int $timeout = 60): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $host) !== 1) {
            throw new RuntimeException('SSH host must be a safe alias from ~/.ssh/config.');
        }

        $process = new Process(
            ['ssh', '-o', 'BatchMode=yes', $host, $command],
            $this->projectRoot,
        );
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());

            throw new RuntimeException($error !== '' ? $error : "SSH command failed with exit code {$process->getExitCode()}.");
        }

        return trim($process->getOutput());
    }
}
