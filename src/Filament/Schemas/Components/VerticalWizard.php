<?php

namespace WireNinja\Accelerator\Filament\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Wizard;

class VerticalWizard extends Wizard
{
    protected string $view = 'accelerator::filament.schemas.components.vertical-wizard';

    protected bool $isSticky = true;

    protected string|Closure|null $navigationHeading = null;

    protected string|Closure|null $navigationDescription = null;

    public function sticky(bool|Closure $condition = true): static
    {
        $this->isSticky = $condition;

        return $this;
    }

    public function isSticky(): bool
    {
        return (bool) $this->evaluate($this->isSticky);
    }

    public function navigationHeading(string|Closure|null $heading): static
    {
        $this->navigationHeading = $heading;

        return $this;
    }

    public function getNavigationHeading(): ?string
    {
        return $this->evaluate($this->navigationHeading);
    }

    public function navigationDescription(string|Closure|null $description): static
    {
        $this->navigationDescription = $description;

        return $this;
    }

    public function getNavigationDescription(): ?string
    {
        return $this->evaluate($this->navigationDescription);
    }
}
