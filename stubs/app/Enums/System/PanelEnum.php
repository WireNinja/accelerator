<?php

declare(strict_types=1);

namespace App\Enums\System;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PanelEnum: string implements HasColor, HasIcon, HasLabel
{
    case Admin = 'admin';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin Panel',
            self::System => 'System Panel',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Admin => 'lucide-shield-check',
            self::System => 'lucide-settings-2',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::System => 'gray',
        };
    }

    public function getUrl(): string
    {
        return url('/'.$this->value);
    }
}
