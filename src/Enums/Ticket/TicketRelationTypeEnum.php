<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums\Ticket;

use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;

enum TicketRelationTypeEnum: string implements HasLabel
{
    use BetterEnum;

    case Duplicate = 'duplicate';
    case BlockedBy = 'blocked_by';
    case RelatedTo = 'related_to';
    case Dependency = 'dependency';

    public function getLabel(): string
    {
        return match ($this) {
            self::Duplicate => 'Duplikat dari',
            self::BlockedBy => 'Diblokir oleh',
            self::RelatedTo => 'Berkaitan dengan',
            self::Dependency => 'Bergantung pada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Duplicate => 'danger',
            self::BlockedBy => 'warning',
            self::RelatedTo => 'info',
            self::Dependency => 'gray',
        };
    }
}
