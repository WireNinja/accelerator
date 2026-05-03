<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DiscoverAsResource
{
    public function __construct(
        public ?string $key = null,
        public ?string $form = null,
        public ?string $table = null,
    ) {}
}
