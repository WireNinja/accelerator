<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment\Concerns;

use WireNinja\Accelerator\Deployment\DeploymentConfig;

use function Laravel\Prompts\confirm;

trait ConfirmsDeployment
{
    private function confirmed(string $action, DeploymentConfig $config): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Remote mutation in non-interactive mode requires --force.');

            return false;
        }

        return confirm(
            "{$action} {$config->stage}: {$config->domain} at {$config->deployRoot} via {$config->sshHost} ({$config->group})?",
            default: false,
        );
    }
}
