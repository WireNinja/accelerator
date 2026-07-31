<?php

declare(strict_types=1);

namespace App\Enums\System;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasIcon, HasLabel
{
    case MasterData = 'Master Data';
    case Operations = 'Operasional';
    case System = 'System';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::MasterData => 'lucide-database',
            self::Operations => 'lucide-workflow',
            self::System => 'lucide-settings-2',
        };
    }
}
