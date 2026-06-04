<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Support\Facades\Http;

/**
 * Sends telemetry notifications to Discord and/or Telegram.
 *
 * Fire-and-forget: notification failures are logged but never crash the app.
 */
final class TelemetryNotifier
{
    /**
     * Send a notification for a new or re-opened exception.
     */
    public function send(string $class, string $file, int $line, string $message, bool $isReopen): void
    {
        $discordWebhook = config('accelerator.telemetry.notify.discord_webhook');
        $telegramChatId = config('accelerator.telemetry.notify.telegram_chat_id');

        if (blank($discordWebhook) && blank($telegramChatId)) {
            return;
        }

        $label = $isReopen ? 'RE-OPENED' : 'NEW';
        $shortMessage = mb_substr($message, 0, 200);
        $shortFile = str_replace(base_path().'/', '', $file);
        $appName = (string) config('app.name', 'App');

        if (filled($discordWebhook)) {
            $this->sendDiscord($discordWebhook, $label, $class, $shortFile, $line, $shortMessage, $appName);
        }

        if (filled($telegramChatId)) {
            $this->sendTelegram($telegramChatId, $label, $class, $shortFile, $line, $shortMessage, $appName);
        }
    }

    /**
     * Send notification via Discord webhook.
     */
    private function sendDiscord(string $webhookUrl, string $label, string $class, string $file, int $line, string $message, string $appName): void
    {
        rescue(fn () => Http::timeout(5)->post($webhookUrl, [
            'content' => null,
            'embeds' => [[
                'title' => "[{$label}] {$class}",
                'description' => "```\n{$message}\n```",
                'color' => $label === 'NEW' ? 0xED4245 : 0xFEE75C, // Red for new, Yellow for reopen
                'fields' => [
                    ['name' => 'File', 'value' => "`{$file}:{$line}`", 'inline' => true],
                    ['name' => 'App', 'value' => $appName, 'inline' => true],
                ],
                'timestamp' => now()->toIso8601String(),
            ]],
        ]));
    }

    /**
     * Send notification via Telegram Bot API.
     */
    private function sendTelegram(string $chatId, string $label, string $class, string $file, int $line, string $message, string $appName): void
    {
        $botToken = config('accelerator.telemetry.notify.telegram_bot_token')
            ?? config('services.telegram-bot-api.token');

        if (blank($botToken)) {
            return;
        }

        $text = implode("\n", [
            "<b>[{$label}] {$class}</b>",
            '',
            "<code>{$message}</code>",
            '',
            "File: <code>{$file}:{$line}</code>",
            "App: {$appName}",
        ]);

        $baseUri = config('accelerator.telemetry.notify.telegram_base_uri')
            ?? 'https://api.telegram.org';

        rescue(fn () => Http::timeout(5)
            ->baseUrl("{$baseUri}/bot{$botToken}")
            ->post('/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]));
    }
}
