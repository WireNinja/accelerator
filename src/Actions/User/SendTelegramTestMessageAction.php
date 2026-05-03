<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Actions\User;

use NotificationChannels\Telegram\Telegram;
use Throwable;
use WireNinja\Accelerator\Contracts\HasHandle;
use WireNinja\Accelerator\Exceptions\BusinessException;
use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Settings\SystemSettings;
use WireNinja\Accelerator\Support\Telegram\TelegramBotConfigurator;

final class SendTelegramTestMessageAction implements HasHandle
{
    public function __construct(
        private readonly Telegram $telegram,
        private readonly SystemSettings $systemSettings,
        private readonly TelegramBotConfigurator $telegramBotConfigurator,
    ) {}

    public function handle(AcceleratedUser $user, string $telegramChatId): void
    {
        $telegramChatId = trim($telegramChatId);

        if ($telegramChatId === '') {
            throw new BusinessException('Telegram Chat ID belum diisi.');
        }

        $botToken = $this->telegramBotConfigurator->getBotToken();

        if ($botToken === null) {
            throw new BusinessException('Telegram Bot Token belum dikonfigurasi di App Settings.');
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

    private function buildMessage(AcceleratedUser $user): string
    {
        $identifier = filled($user->username)
            ? sprintf('Username: %s', $user->username)
            : sprintf('Email: %s', $user->email);

        return implode("\n", [
            'Tes koneksi Telegram berhasil.',
            sprintf('Aplikasi: %s', $this->systemSettings->brand_name),
            sprintf('Pengguna: %s', $user->name),
            $identifier,
            sprintf('Waktu: %s', now()->format('d M Y H:i:s')),
        ]);
    }
}
