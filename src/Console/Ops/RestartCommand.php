<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:restart {service=all : Service name or all} {--stage= : Deployment stage}')]
#[Description('Restart deployment services for a stage')]
final class RestartCommand extends Command
{
    public function handle(): int
    {
        try {
            $stage = DeployConfig::stage($this->option('stage'));
        } catch (\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $service = (string) $this->argument('service');
        $target = $service === 'all'
            ? "{$stage['group']}:*"
            : "{$stage['group']}:".DeployConfig::programName($stage, $service);

        return $this->runProcess(['sudo', 'supervisorctl', 'restart', $target]);
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command): int
    {
        $process = new Process($command);
        $process->setTty(Process::isTtySupported());
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
