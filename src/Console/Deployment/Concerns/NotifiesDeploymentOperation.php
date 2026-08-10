<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment\Concerns;

use WireNinja\Accelerator\Deployment\DeploymentOperatorNotifier;

trait NotifiesDeploymentOperation
{
    /** @param array<string, scalar|null> $context */
    private function notifyOperation(
        string $stage,
        string $operation,
        string $result,
        float $startedAt,
        array $context = [],
        bool $force = false,
    ): void {
        if ($result === 'failed' && ! array_key_exists('next_command', $context)) {
            $context['next_command'] = "php artisan accelerator:deploy:status --stage={$stage} --json";
        }

        (new DeploymentOperatorNotifier(base_path()))->send(
            stage: $stage,
            operation: $operation,
            result: $result,
            context: ['duration_seconds' => round(microtime(true) - $startedAt, 2), ...$context],
            force: $force,
        );
    }
}
