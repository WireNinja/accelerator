<?php

namespace WireNinja\Accelerator\Exceptions;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

class BusinessException extends DomainException implements ShouldntReport
{
    public const DEFAULT_NOTIFICATION_TITLE = 'Aksi tidak dapat diproses';

    public const FILAMENT_STATUS_CODE = 460;

    public function getNotificationTitle(): string
    {
        return self::DEFAULT_NOTIFICATION_TITLE;
    }

    public function getNotificationBody(): ?string
    {
        return blank($this->getMessage()) ? null : $this->getMessage();
    }

    public function getFilamentStatusCode(): int
    {
        return self::FILAMENT_STATUS_CODE;
    }
}
