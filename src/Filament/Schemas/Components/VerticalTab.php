<?php

namespace WireNinja\Accelerator\Filament\Schemas\Components;

use Filament\Schemas\Components\Tabs;

class VerticalTab extends Tabs
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->vertical();
    }
}
