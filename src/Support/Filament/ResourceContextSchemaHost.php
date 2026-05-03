<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Filament;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

class ResourceContextSchemaHost extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function render(): string
    {
        return '';
    }
}
