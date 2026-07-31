<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Concerns;

trait RoleEnumPermissions
{
    /** @return list<string>|null Null grants every generated permission. */
    abstract public function defaultPermissions(): ?array;

    /** @return list<self> */
    public static function rolesReceivingAllPermissionsByDefault(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::SuperAdmin && $role->defaultPermissions() === null,
        ));
    }

    /** @return list<self> */
    public static function rolesWithSpecificPermissions(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::SuperAdmin && $role->defaultPermissions() !== null,
        ));
    }
}
