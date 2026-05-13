<?php

namespace App\Enums\System;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;
use WireNinja\Accelerator\Constant\PanelColor;

enum PanelEnum: string implements HasColor, HasIcon, HasLabel
{
    use BetterEnum;

    case Admin = 'admin';
    case Production = 'production';
    case Sales = 'sales';
    case Accounting = 'accounting';
    case App = 'app';
    case Support = 'support';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin Panel',
            self::Production => 'Production Panel',
            self::Sales => 'Sales Panel',
            self::Accounting => 'Accounting Panel',
            self::App => 'App Panel',
            self::Support => 'Support Panel',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Admin => 'lucide-shield-check',
            self::Production => 'lucide-factory',
            self::Sales => 'lucide-shopping-cart',
            self::Accounting => 'lucide-banknote',
            self::App => 'lucide-layout-grid',
            self::Support => 'lucide-life-buoy',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => PanelColor::Danger,
            self::Production => PanelColor::Primary,
            self::Sales => PanelColor::Warning,
            self::Accounting => PanelColor::Success,
            self::App => PanelColor::Primary,
            self::Support => PanelColor::Info,
        };
    }

    public function getUrl(): string
    {
        return url('/'.$this->value);
    }
}
