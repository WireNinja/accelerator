<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Concerns;

trait HasBasicCrudPermissions
{
    /**
     * @return array<int, string>
     */
    protected static function basicCrudPermissions(): array
    {
        return [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
        ];
    }
}
