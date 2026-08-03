<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Deployment\Deployer;

final class LogsCommand extends Command
{
    protected $signature = 'accelerator:logs {service=laravel} {--stage=production} {--lines=200}';

    protected $description = 'Read a stage-owned Laravel or Supervisor service log';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $service = (string) $this->argument('service');
            $lines = filter_var($this->option('lines'), FILTER_VALIDATE_INT);

            if (! is_int($lines) || $lines < 1 || $lines > 5000) {
                throw new RuntimeException('--lines must be between 1 and 5000.');
            }

            (new Deployer(base_path()))->run(
                'accelerator:logs',
                $stage,
                ["--service={$service}", "--lines={$lines}"],
            );

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
