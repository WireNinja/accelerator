<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Override;

class LocationPicker extends Field
{
    #[Override]
    protected string $view = 'accelerator::forms.components.location-picker';

    protected string $longitudeField = 'longitude';

    public function longitudeField(string $field): static
    {
        $this->longitudeField = $field;

        return $this;
    }

    public function getLongitudeField(): string
    {
        return $this->longitudeField;
    }
}
