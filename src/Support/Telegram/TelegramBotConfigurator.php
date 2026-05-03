<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Telegram;

use Illuminate\Support\Facades\Config;
use NotificationChannels\Telegram\Telegram;
use WireNinja\Accelerator\Settings\SystemSettings;

final class TelegramBotConfigurator
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
    ) {}

    public function syncConfig(): bool
    {
        if ($botToken = $this->getBotToken()) {
            Config::set('services.telegram.token', $botToken);
        }

        if ($apiBaseUri = $this->getApiBaseUri()) {
            Config::set('services.telegram.base_uri', $apiBaseUri);
        }

        return true;
    }

    public function configureClient(Telegram $telegram): Telegram
    {
        if ($botToken = $this->getBotToken()) {
            $telegram->setToken($botToken);
        }

        if ($apiBaseUri = $this->getApiBaseUri()) {
            $telegram->setApiBaseUri($apiBaseUri);
        }

        return $telegram;
    }

    public function getBotToken(): ?string
    {
        $botToken = $this->systemSettings->telegram_bot_token
            ?? config('services.telegram.token');

        return filled($botToken) ? (string) $botToken : null;
    }

    public function getApiBaseUri(): ?string
    {
        $apiBaseUri = $this->systemSettings->telegram_api_base_uri
            ?? config('services.telegram.base_uri');

        return filled($apiBaseUri) ? (string) $apiBaseUri : null;
    }
}
