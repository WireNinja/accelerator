<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DiscoverAsForm
{
    public function __construct(
        public ?string $resource = null,
    ) {}
}
