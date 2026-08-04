<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Actions\User;

use Illuminate\Database\Eloquent\Model;
use NotificationChannels\Telegram\Telegram;
use Throwable;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Exceptions\BusinessException;
use WireNinja\Accelerator\Settings\SystemSettings;
use WireNinja\Accelerator\Support\Cast;
use WireNinja\Accelerator\Support\Telegram\TelegramBotConfigurator;

final class SendTelegramTestMessageAction
{
    public function __construct(
        private readonly Telegram $telegram,
        private readonly SystemSettings $systemSettings,
        private readonly TelegramBotConfigurator $telegramBotConfigurator,
    ) {}

    public function handle(Model&AcceleratorUser $user, string $telegramChatId): void
    {
        if (! config('accelerator.features.telegram', false)) {
            throw new BusinessException('Integrasi Telegram belum diaktifkan untuk environment ini.');
        }

        $telegramChatId = trim($telegramChatId);

        if ($telegramChatId === '') {
            throw new BusinessException('Telegram Chat ID belum diisi.');
        }

        $botToken = $this->telegramBotConfigurator->getBotToken();

        if ($botToken === null) {
            throw new BusinessException('TELEGRAM_BOT_TOKEN belum dikonfigurasi di environment aplikasi.');
        }

        $this->telegramBotConfigurator->configureClient($this->telegram);

        try {
            $this->telegram->sendMessage([
                'chat_id' => $telegramChatId,
                'text' => $this->buildMessage($user),
            ]);
        } catch (Throwable $throwable) {
            throw new BusinessException(
                'Gagal mengirim pesan uji Telegram. Pastikan chat id benar dan user sudah pernah mengirim pesan ke bot.',
                previous: $throwable,
            );
        }
    }

    private function buildMessage(Model&AcceleratorUser $user): string
    {
        $username = Cast::asString($user->getAttribute('username'));
        $identifier = $username !== ''
            ? sprintf('Username: %s', $username)
            : sprintf('Email: %s', Cast::asString($user->getAttribute('email')));

        return implode("\n", [
            'Tes koneksi Telegram berhasil.',
            sprintf('Aplikasi: %s', $this->systemSettings->brand_name),
            sprintf('Pengguna: %s', Cast::asString($user->getAttribute('name'))),
            $identifier,
            sprintf('Waktu: %s', now()->format('d M Y H:i:s')),
        ]);
    }
}
