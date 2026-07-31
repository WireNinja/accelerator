<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Deployment\Deployer;

final class DeployStatusCommand extends Command
{
    protected $signature = 'accelerator:deploy:status {--stage=production} {--json}';

    protected $description = 'Read deployment state without changing the server';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $output = (new Deployer(base_path()))->run('accelerator:status', $stage, capture: true);

            if ($this->option('json')) {
                $this->output->writeln(json_encode(['stage' => $stage, 'output' => $output], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->output->writeln($output);
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
