<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ProcessRunner
{
    public function __construct(private readonly bool $quiet = false) {}

    /**
     * @param  list<string>  $command
     */
    public function run(array $command, string $workingDirectory): void
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(null);
        $process->run(function (string $type, string $output): void {
            if (! $this->quiet) {
                fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
            }
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Command failed with exit code %d: %s%s',
                $process->getExitCode() ?? 1,
                $this->redactedCommandLine($command),
                $this->quiet ? $this->failureOutput($process) : '',
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

    private function failureOutput(Process $process): string
    {
        $output = trim($process->getErrorOutput().PHP_EOL.$process->getOutput());

        return $output === '' ? '' : PHP_EOL.$output;
    }

    /** @param list<string> $command */
    private function redactedCommandLine(array $command): string
    {
        return implode(' ', array_map(
            static fn (string $argument): string => preg_match('/(?:password|secret|token|key)=/i', $argument) === 1
                ? preg_replace('/=.*/', '=[redacted]', $argument) ?? '[redacted]'
                : $argument,
            $command,
        ));
    }
}
