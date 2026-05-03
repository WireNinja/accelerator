<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums\Ticket;

use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;

enum TicketPriorityEnum: string implements HasLabel
{
    use BetterEnum;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Urgent => 'Urgent',
        };
    }
}
