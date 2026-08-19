<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Telegram;

use NotificationChannels\Telegram\Telegram;
use WireNinja\Accelerator\Support\Cast;

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

        return Cast::filledString($botToken);
    }

    public function getApiBaseUri(): ?string
    {
        return Cast::filledString(config('services.telegram.base_uri'));
    }
}
