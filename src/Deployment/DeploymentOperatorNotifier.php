<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use Illuminate\Support\Facades\Log;
use Throwable;
use WireNinja\Accelerator\Support\Operations\OperatorTelegramNotifier;

final readonly class DeploymentOperatorNotifier
{
    public function __construct(private string $projectRoot) {}

    /** @param array<string, scalar|null> $context */
    public function send(string $stage, string $operation, string $result, array $context = [], bool $force = false): bool
    {
        try {
            $config = DeploymentConfig::load($this->projectRoot, $stage);
            $environment = (new DeploymentEnvironment($this->projectRoot))->read($config);

            return (new OperatorTelegramNotifier)->sendWith(
                token: trim($environment['ACCELERATOR_TELEGRAM_BOT_TOKEN'] ?? ''),
                chatId: trim($environment['ACCELERATOR_TELEGRAM_CHAT_ID'] ?? ''),
                operation: $operation,
                result: $result,
                identity: [
                    'deployment_key' => $config->deploymentKey,
                    'stage' => $stage,
                    'domain' => $config->domain,
                    'notify_successes' => filter_var($environment['ACCELERATOR_TELEGRAM_NOTIFY_SUCCESSES'] ?? false, FILTER_VALIDATE_BOOL),
                ],
                context: $context,
                force: $force,
            );
        } catch (Throwable $exception) {
            Log::warning('Accelerator deployment notification could not be prepared.', [
                'stage' => $stage,
                'operation' => $operation,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
