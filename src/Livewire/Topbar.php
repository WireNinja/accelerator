<?php

namespace WireNinja\Accelerator\Livewire;

use Illuminate\Contracts\View\View;

class Topbar extends \Filament\Livewire\Topbar
{
    public function render(): View
    {
        return view()->file(__DIR__.'/../../resources/views/livewire/topbar.blade.php');
    }
}
