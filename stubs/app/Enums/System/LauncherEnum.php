<?php

declare(strict_types=1);

namespace App\Enums\System;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use WireNinja\Accelerator\Concerns\BetterEnum;

enum LauncherEnum: string implements HasIcon, HasLabel
{
    use BetterEnum;

    case PDF = 'pdf';

    public function getLabel(): string
    {
        return match ($this) {
            self::PDF => 'PDF Tools',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PDF => 'lucide-file-text',
        };
    }

    public function getUrl(): string
    {
        return match ($this) {
            self::PDF => 'https://pdf.waringin.cloud/#tools-header',
        };
    }
}
