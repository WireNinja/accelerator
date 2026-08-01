<?php

declare(strict_types=1);

namespace App\Enums\System;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case MasterData = 'Master Data';
    case Operations = 'Operasional';
    case System = 'System';

    public function getLabel(): string
    {
        return $this->value;
    }
}
