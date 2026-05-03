<?php

namespace App\Enums\System;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use WireNinja\Accelerator\Concerns\BetterEnum;
use WireNinja\Accelerator\Concerns\HasBasicCrudPermissions;
use WireNinja\Accelerator\Enums\BuiltinSystemResource;
use WireNinja\Accelerator\Enums\Concerns\MustBeResourceEnum;
use WireNinja\Accelerator\Filament\Resources\TicketBoards\TicketBoardResource;
use WireNinja\Accelerator\Filament\Resources\Tickets\TicketResource;

enum ResourceEnum: string implements MustBeResourceEnum
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
        return match ($this) {
            self::RoleResource => RoleResource::class,
            self::TicketBoardResource => TicketBoardResource::class,
            self::TicketResource => TicketResource::class,
        };
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
            self::RoleResource => 'admin',
            self::TicketBoardResource => 'admin',
            self::TicketResource => 'admin',
        };
    }

    public static function fromResource(string $resource): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getResource() === $resource) {
                return $case;
            }
        }

        return null;
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
