<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Forms\Components;

use Filament\Forms\Components\Checkbox;

class BooleanCard extends Checkbox
{
    protected string $view = 'accelerator::forms.components.boolean-card';

    protected string $trueLabel = 'Aktif';

    protected ?string $trueDescription = null;

    protected ?string $icon = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
    }

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function getTrueLabel(): string
    {
        return $this->trueLabel;
    }

    public function trueDescription(string $description): static
    {
        $this->trueDescription = $description;

        return $this;
    }

    public function getTrueDescription(): ?string
    {
        return $this->trueDescription;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}
