<?php

namespace WireNinja\Accelerator\Filament\Schemas\Components;

use Filament\Schemas\Components\Wizard;

class VerticalWizard extends Wizard
{
    protected string $view = 'accelerator::filament.schemas.components.vertical-wizard';

    protected bool $isSticky = true;

    public function sticky(bool $condition = true): static
    {
        $this->isSticky = $condition;

        return $this;
    }

    public function isSticky(): bool
    {
        return $this->isSticky;
    }
}
