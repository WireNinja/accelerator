<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Concerns;

trait RoleEnumPermissions
{
    /**
     * Returns the list of permission names that should be assigned to this role by default.
     * Return an empty array to grant ALL generated permissions (e.g. for Admin).
     * SuperAdmin is always excluded — it bypasses Gate entirely.
     *
     * @return string[]
     */
    abstract public function defaultPermissions(): array;

    /**
     * Roles that should receive ALL generated permissions.
     *
     * @return self[]
     */
    public static function rolesReceivingAllPermissionsByDefault(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::SuperAdmin && $role->defaultPermissions() === [],
        ));
    }

    /**
     * Roles with a specific (restricted) permission set.
     *
     * @return self[]
     */
    public static function rolesWithSpecificPermissions(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::SuperAdmin && $role->defaultPermissions() !== [],
        ));
    }
}
