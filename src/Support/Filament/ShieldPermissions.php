<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Filament;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;

final class ShieldPermissions
{
    /** @return list<string> */
    public static function crud(string ...$additional): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', ...$additional];
    }

    /** @return array<class-string, list<string>> */
    public static function builtIn(): array
    {
        $permissions = [RoleResource::class => self::crud()];

        if (! config('accelerator.features.ticketing', false)) {
            return $permissions;
        }

        return [
            ...$permissions,
            TicketBoardResource::class => self::crud(),
            TicketResource::class => self::crud(
                'viewAll',
                'viewOwn',
                'viewAssigned',
                'updateOwn',
                'updateAssigned',
                'deleteOwn',
                'assign',
                'changeStatus',
            ),
        ];
    }
}
