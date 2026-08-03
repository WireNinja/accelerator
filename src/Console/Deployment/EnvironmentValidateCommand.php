<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;

final class EnvironmentValidateCommand extends Command
{
    protected $signature = 'accelerator:env:validate {--stage=production} {--json}';

    protected $description = 'Validate one canonical local stage environment without SSH';

    /** @throws JsonException */
    public function handle(): int
    {
        $stage = (string) $this->option('stage');

        try {
            $config = DeploymentConfig::load(base_path(), $stage, validateRuntime: false);
            $errors = (new DeploymentEnvironment(base_path()))->validate($config);

            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'schema' => 1,
                    'status' => $errors === [] ? 'OK' : 'ERROR',
                    'stage' => $stage,
                    'errors' => $errors,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } elseif ($errors === []) {
                $this->components->info("{$stage} environment is valid.");
            } else {
                $this->components->error(implode(PHP_EOL, $errors));
            }

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
