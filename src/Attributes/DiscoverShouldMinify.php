<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DiscoverShouldMinify
{
    public function __construct(
        public ?string $reason = null,
    ) {}
}
