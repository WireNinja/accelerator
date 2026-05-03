<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums\Ticket;

use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;

enum TicketTypeEnum: string implements HasLabel
{
    use BetterEnum;

    case Question = 'question';
    case Help = 'help';
    case Feature = 'feature';

    public function getLabel(): string
    {
        return match ($this) {
            self::Question => 'Pertanyaan',
            self::Help => 'Butuh Bantuan',
            self::Feature => 'Permintaan Fitur',
        };
    }
}
