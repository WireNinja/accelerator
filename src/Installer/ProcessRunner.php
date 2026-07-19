<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, string $workingDirectory): void
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(null);
        $process->run(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Command failed with exit code %d: %s',
                $process->getExitCode() ?? 1,
                $process->getCommandLine(),
            ));
        }
    }

    /**
     * @param  list<string>  $command
     */
    public function capture(array $command, string $workingDirectory): string
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : '';
    }

    public function commandExists(string $command): bool
    {
        return (new ExecutableFinder)->find($command) !== null;
    }
}
