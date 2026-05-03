<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums\Ticket;

use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;

enum TicketCustomFieldTypeEnum: string implements HasLabel
{
    use BetterEnum;

    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Number => 'Angka',
            self::Date => 'Tanggal',
            self::Select => 'Pilihan',
        };
    }
}
