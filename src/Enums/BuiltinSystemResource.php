<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use WireNinja\Accelerator\Concerns\HasBasicCrudPermissions;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;

final class BuiltinSystemResource
{
    use HasBasicCrudPermissions;

    /**
     * @return array<class-string, array<int, string>>
     */
    public static function all(): array
    {
        return [
            RoleResource::class => [
                ...self::basicCrudPermissions(),
            ],
            TicketBoardResource::class => [
                ...self::basicCrudPermissions(),
            ],
            TicketResource::class => [
                ...self::basicCrudPermissions(),
                'viewAll',
                'viewOwn',
                'viewAssigned',
                'updateOwn',
                'updateAssigned',
                'deleteOwn',
                'assign',
                'changeStatus',
            ],
        ];
    }
}
