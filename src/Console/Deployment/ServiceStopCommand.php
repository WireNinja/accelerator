<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class ServiceStopCommand extends Command
{
    use ConfirmsDeployment;

    protected $signature = 'accelerator:service:stop {service=all} {--stage=production} {--force}';

    protected $description = 'Stop one configured service or the exact stage group';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $service = (string) $this->argument('service');
            $config = DeploymentConfig::load(base_path(), $stage);

            if ($service !== 'all' && ! in_array($service, $config->supervisorServices(), true)) {
                throw new RuntimeException('Service must be all or one of: '.implode(', ', $config->supervisorServices()));
            }

            if (! $this->confirmed("Stop {$service} on", $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('accelerator:service-stop', $stage, ["--service={$service}"]);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
