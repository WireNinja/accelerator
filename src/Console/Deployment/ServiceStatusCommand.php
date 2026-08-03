<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class ServiceStatusCommand extends Command
{
    protected $signature = 'accelerator:service:status {service=all} {--stage=production} {--json}';

    protected $description = 'Read exact stage Supervisor service status';

    /** @throws JsonException */
    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $service = (string) $this->argument('service');
            $config = DeploymentConfig::load(base_path(), $stage);
            $this->assertService($config, $service);
            $output = (new Deployer(base_path()))->run(
                'accelerator:service-status',
                $stage,
                ["--service={$service}"],
                capture: true,
            );
            $lines = array_values(array_filter(preg_split('/\R/', $output) ?: [], static fn (string $line): bool => str_contains($line, ' RUNNING ') || str_contains($line, ' STOPPED ') || str_contains($line, ' FATAL ') || str_contains($line, ' BACKOFF ') || str_contains($line, ' EXITED ')));

            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'schema' => 1,
                    'status' => $lines !== [] ? 'OK' : 'WARNING',
                    'stage' => $stage,
                    'service' => $service,
                    'supervisor' => $lines,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $this->line($lines !== [] ? implode(PHP_EOL, $lines) : $output);
            }

            return $lines !== [] ? self::SUCCESS : self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertService(DeploymentConfig $config, string $service): void
    {
        if ($service !== 'all' && ! in_array($service, $config->supervisorServices(), true)) {
            throw new RuntimeException('Service must be all or one of: '.implode(', ', $config->supervisorServices()));
        }
    }
}
