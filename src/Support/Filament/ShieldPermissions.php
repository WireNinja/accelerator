<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Filament;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;

final class ShieldPermissions
{
    /** @return list<string> */
    public static function crud(string ...$additional): array
    {
        return array_values(['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', ...$additional]);
    }

    /** @return array<class-string, list<string>> */
    public static function builtIn(): array
    {
        return [RoleResource::class => self::crud()];
    }
}
