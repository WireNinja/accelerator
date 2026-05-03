<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums\Ticket;

use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;

enum TicketStatusEnum: string implements HasLabel
{
    use BetterEnum;

    case Open = 'open';
    case Triaged = 'triaged';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case WaitingClient = 'waiting_client';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Triaged => 'Triaged',
            self::Planned => 'Planned',
            self::InProgress => 'In Progress',
            self::WaitingClient => 'Waiting Client',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }
}
