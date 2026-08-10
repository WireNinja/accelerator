<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Operations;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NotificationChannels\Telegram\Telegram;
use Throwable;

final class OperatorTelegramNotifier
{
    /** @param array<string, scalar|null> $context */
    public function send(string $operation, string $result, array $context = [], bool $force = false): bool
    {
        $token = trim((string) config('accelerator.operations.telegram.bot_token', ''));
        $chatId = trim((string) config('accelerator.operations.telegram.chat_id', ''));

        return $this->sendWith(
            token: $token,
            chatId: $chatId,
            operation: $operation,
            result: $result,
            identity: [
                'deployment_key' => (string) config('accelerator.operations.deployment_key', config('app.name')),
                'stage' => (string) config('accelerator.operations.stage', config('app.env')),
                'domain' => (string) config('accelerator.operations.domain', config('app.url')),
                'notify_successes' => (bool) config('accelerator.operations.telegram.notify_successes', false),
                'base_uri' => (string) config('accelerator.operations.telegram.base_uri', 'https://api.telegram.org'),
            ],
            context: $context,
            force: $force,
        );
    }

    /**
     * @param  array{deployment_key: string, stage: string, domain: string, notify_successes: bool, base_uri?: string}  $identity
     * @param  array<string, scalar|null>  $context
     */
    public function sendWith(
        string $token,
        string $chatId,
        string $operation,
        string $result,
        array $identity,
        array $context = [],
        bool $force = false,
    ): bool {

        if ($token === '' || $chatId === '') {
            return false;
        }

        if (! $force && $result === 'success' && ! $identity['notify_successes']) {
            return false;
        }

        try {
            $telegram = new Telegram(
                token: $token,
                http: new Client(['connect_timeout' => 5, 'timeout' => 10]),
                apiBaseUri: $identity['base_uri'] ?? 'https://api.telegram.org',
            );
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $this->message($operation, $result, $identity, $context),
                'disable_web_page_preview' => true,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Accelerator operator Telegram notification failed.', [
                'operation' => $operation,
                'result' => $result,
                'error' => Str::limit($exception->getMessage(), 300),
            ]);

            return false;
        }
    }

    public function configured(): bool
    {
        return filled(config('accelerator.operations.telegram.bot_token'))
            && filled(config('accelerator.operations.telegram.chat_id'));
    }

    /**
     * @param  array{deployment_key: string, stage: string, domain: string, notify_successes: bool, base_uri?: string}  $identity
     * @param  array<string, scalar|null>  $context
     */
    private function message(string $operation, string $result, array $identity, array $context): string
    {
        $stage = $identity['stage'];
        $label = match ($stage) {
            'production' => 'LIVE DATA',
            'staging' => 'TEST DATA',
            default => 'LOCAL DATA',
        };
        $lines = [
            sprintf('%s %s', $result === 'success' ? '✅' : ($result === 'started' ? '⏳' : '❌'), Str::headline($operation)),
            sprintf('Project: %s', $identity['deployment_key']),
            sprintf('Stage: %s (%s)', $stage, $label),
            sprintf('Domain: %s', $identity['domain']),
            sprintf('Result: %s', Str::upper($result)),
            sprintf('Time: %s', now()->toIso8601String()),
        ];
        $revisionPath = base_path('REVISION');
        $revision = is_file($revisionPath) ? trim((string) file_get_contents($revisionPath)) : '';

        if (preg_match('/^[a-f0-9]{40,64}$/i', $revision) === 1) {
            $lines[] = 'Revision: '.$revision;
        }

        foreach ($context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $redacted = (string) preg_replace(
                '/(password|secret|token|key)(\s*[:=]\s*)[^\s]+/i',
                '$1$2[redacted]',
                (string) $value,
            );
            $lines[] = Str::headline($key).': '.Str::limit($redacted, 800);
        }

        return Str::limit(implode("\n", $lines), 4000, '…');
    }
}
