<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Telegram;

use NotificationChannels\Telegram\Telegram;

final class TelegramBotConfigurator
{
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
        $botToken = config('services.telegram.token');

        return filled($botToken) ? (string) $botToken : null;
    }

    public function getApiBaseUri(): ?string
    {
        $apiBaseUri = config('services.telegram.base_uri');

        return filled($apiBaseUri) ? (string) $apiBaseUri : null;
    }
}
