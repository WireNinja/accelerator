<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:rollback {--stage= : Deployment stage}')]
#[Description('Rollback current symlink to the previous release')]
final class RollbackCommand extends Command
{
    public function handle(): int
    {
        try {
            $stage = DeployConfig::stage($this->option('stage'));
        } catch (\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $paths = $stage['paths'];
        $current = is_link($paths['current']) ? readlink($paths['current']) : null;
        $releases = collect(glob("{$paths['releases']}/*") ?: [])
            ->filter(fn (string $path): bool => is_dir($path))
            ->sort()
            ->values();

        $previous = $releases
            ->reject(fn (string $path): bool => $current === $path)
            ->last();

        if (! is_string($previous)) {
            $this->components->error('No previous release found.');

            return self::FAILURE;
        }

        $next = "{$paths['root']}/current.rollback";

        if (file_exists($next) || is_link($next)) {
            rename($next, "{$paths['archive']}/current.rollback.".now()->format('Y-m-d_H-i-s'));
        }

        symlink($previous, $next);

        if (file_exists($paths['current']) || is_link($paths['current'])) {
            rename($paths['current'], "{$paths['archive']}/current.before_rollback.".now()->format('Y-m-d_H-i-s'));
        }

        rename($next, $paths['current']);

        $process = new Process(['sudo', 'supervisorctl', 'restart', "{$stage['group']}:*"]);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        $this->components->info("Rolled back [{$stage['name']}] to [{$previous}].");

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
