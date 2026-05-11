<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Attributes;

use Attribute;

/**
 * @deprecated This attribute is no longer used. Scanning now focuses on class and component names only.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class DiscoverShouldMinify
{
    public function __construct(
        public ?string $reason = null,
    ) {}
}
