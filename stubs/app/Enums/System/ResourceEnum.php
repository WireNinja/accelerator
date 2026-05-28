<?php

declare(strict_types=1);

namespace App\Enums\System;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Support\Contracts\HasColor;
use WireNinja\Accelerator\Concerns\BetterEnum;
use WireNinja\Accelerator\Concerns\HasBasicCrudPermissions;
use WireNinja\Accelerator\Enums\BuiltinSystemResource;
use WireNinja\Accelerator\Enums\Concerns\MustBeResourceEnum;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;

enum ResourceEnum: string implements HasColor, MustBeResourceEnum
{
    use BetterEnum;
    use HasBasicCrudPermissions;

    case RoleResource = RoleResource::class;
    case TicketBoardResource = TicketBoardResource::class;
    case TicketResource = TicketResource::class;

    public function getLabel(): string
    {
        return match ($this) {
            self::RoleResource => 'Peran',
            self::TicketBoardResource => 'Papan Tiket',
            self::TicketResource => 'Tiket',
        };
    }

    public function getResource(): string
    {
        return $this->value;
    }

    public function getNavigationIcon(): string
    {
        return match ($this) {
            self::RoleResource => 'lucide-shield',
            self::TicketBoardResource => 'lucide-layout-board',
            self::TicketResource => 'lucide-ticket',
        };
    }

    public function getNavigationGroup(): string
    {
        return match ($this) {
            self::RoleResource => 'Pengaturan',
            self::TicketBoardResource => 'Tiket',
            self::TicketResource => 'Tiket',
        };
    }

    public function getPanelGroup(): string
    {
        return match ($this) {
            self::RoleResource => PanelEnum::System->value,
            self::TicketBoardResource => PanelEnum::Support->value,
            self::TicketResource => PanelEnum::Support->value,
        };
    }

    public static function fromResource(string $resource): ?self
    {
        return self::tryFrom($resource);
    }

    /**
     * @return array<string, string[]>
     */
    public static function getResourcesPermissions(): array
    {
        return [
            ...BuiltinSystemResource::all(),
        ];
    }
}
