<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums\Contracts;

use BackedEnum;

interface HasStateFlow
{
    /**
     * @TODO implement real proper flow state.
     *
     * @return array<int|string, list<BackedEnum|string|int>>
     */
    public static function stateFlowMap(): array;
}
