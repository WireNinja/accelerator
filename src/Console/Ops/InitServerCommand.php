<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:init-server {--stage= : Deployment stage}')]
#[Description('Initialize deployment directories for a stage')]
final class InitServerCommand extends Command
{
    public function handle(): int
    {
        try {
            $stage = DeployConfig::stage($this->option('stage'));
        } catch (\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($stage['paths'] as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
                $this->line("Created {$path}");
            }
        }

        foreach (['storage', 'storage/app', 'storage/app/public', 'storage/framework', 'storage/logs'] as $directory) {
            $path = "{$stage['paths']['shared']}/{$directory}";

            if (! is_dir($path)) {
                mkdir($path, 0755, true);
                $this->line("Created {$path}");
            }
        }

        $this->components->info("Deployment directories initialized for stage [{$stage['name']}].");

        return self::SUCCESS;
    }
}
