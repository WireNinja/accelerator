<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class TelemetryNotifier
{
    /**
     * @return list<string>
     */
    public function channels(): array
    {
        $channels = [];

        if ($this->discordWebhook() !== null) {
            $channels[] = 'discord';
        }

        if ($this->telegramChatId() !== null && $this->telegramBotToken() !== null) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    /**
     * @return list<string>
     */
    public function configurationIssues(): array
    {
        $issues = [];
        $chatId = $this->telegramChatId();
        $botToken = $this->telegramBotToken();

        if (($chatId === null) !== ($botToken === null)) {
            $issues[] = 'Telegram requires both chat ID and bot token.';
        }

        return $issues;
    }

    public function deliverPending(TelemetryStore $store, TelemetryBuffer $buffer): void
    {
        $limit = max(1, (int) config('accelerator.telemetry.notifications_per_flush', 10));

        foreach ($store->claimNotifications($limit) as $notification) {
            try {
                $payload = json_decode((string) $notification['payload'], true, 32, JSON_THROW_ON_ERROR);

                if (! is_array($payload)) {
                    throw new RuntimeException('Notification outbox payload is not an object.');
                }

                match ($notification['channel']) {
                    'discord' => $this->sendDiscord($payload),
                    'telegram' => $this->sendTelegram($payload),
                    default => throw new RuntimeException('Unknown telemetry notification channel.'),
                };

                $store->markNotificationDelivered(
                    (int) $notification['id'],
                    (int) $notification['group_id'],
                    (string) $notification['lock_token'],
                );
            } catch (Throwable $exception) {
                $store->markNotificationFailed(
                    (int) $notification['id'],
                    (string) $notification['lock_token'],
                    (int) $notification['attempts'],
                    $exception->getMessage(),
                );
                $buffer->recordNotificationFailure($exception);
                rescue(fn () => logger()->warning('[Telemetry] Notification delivery failed and remains in the outbox.', [
                    'channel' => $notification['channel'],
                    'outbox_id' => $notification['id'],
                    'error' => SensitiveDataFilter::text($exception->getMessage(), 500),
                ]));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendDiscord(array $payload): void
    {
        $webhook = $this->discordWebhook();

        if ($webhook === null) {
            throw new RuntimeException('Discord webhook is no longer configured.');
        }

        $label = ($payload['event'] ?? null) === 'reopened' ? 'REOPENED' : 'NEW';
        $message = str_replace('```', "'''", mb_substr((string) ($payload['message'] ?? ''), 0, 500));
        $response = Http::connectTimeout(1)->timeout(3)->post($webhook, [
            'embeds' => [[
                'title' => "[{$label}] ".class_basename((string) ($payload['class'] ?? 'Exception')),
                'description' => "```\n{$message}\n```",
                'color' => $label === 'NEW' ? 0xED4245 : 0xFEE75C,
                'fields' => [
                    [
                        'name' => 'Source',
                        'value' => '`'.($payload['file'] ?? 'unknown').':'.($payload['line'] ?? 0).'`',
                        'inline' => true,
                    ],
                    ['name' => 'App', 'value' => (string) config('app.name', 'App'), 'inline' => true],
                ],
                'timestamp' => $payload['occurred_at'] ?? now()->utc()->toISOString(),
            ]],
        ]);
        $response->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendTelegram(array $payload): void
    {
        $chatId = $this->telegramChatId();
        $botToken = $this->telegramBotToken();

        if ($chatId === null || $botToken === null) {
            throw new RuntimeException('Telegram chat ID or bot token is no longer configured.');
        }

        $label = ($payload['event'] ?? null) === 'reopened' ? 'REOPENED' : 'NEW';
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = implode("\n", [
            '<b>['.$label.'] '.$escape(class_basename((string) ($payload['class'] ?? 'Exception'))).'</b>',
            '',
            '<code>'.$escape(mb_substr((string) ($payload['message'] ?? ''), 0, 500)).'</code>',
            '',
            'Source: <code>'.$escape($payload['file'] ?? 'unknown').':'.$escape($payload['line'] ?? 0).'</code>',
            'App: '.$escape(config('app.name', 'App')),
        ]);
        $baseUri = rtrim((string) config('accelerator.telemetry.notify.telegram_base_uri', 'https://api.telegram.org'), '/');
        $response = Http::connectTimeout(1)
            ->timeout(3)
            ->post("{$baseUri}/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        $response->throw();
    }

    private function discordWebhook(): ?string
    {
        return $this->configuredString('accelerator.telemetry.notify.discord_webhook');
    }

    private function telegramChatId(): ?string
    {
        return $this->configuredString('accelerator.telemetry.notify.telegram_chat_id');
    }

    private function telegramBotToken(): ?string
    {
        return $this->configuredString('accelerator.telemetry.notify.telegram_bot_token')
            ?? $this->configuredString('services.telegram-bot-api.token');
    }

    private function configuredString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
