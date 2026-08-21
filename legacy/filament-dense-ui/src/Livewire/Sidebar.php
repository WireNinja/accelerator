<?php

namespace WireNinja\Accelerator\Livewire;

use BackedEnum;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Sidebar extends Component
{
    #[On('refresh-sidebar')]
    public function refresh(): void {}

    /**
     * @return array<BackedEnum>
     */
    public function getPanels(): array
    {
        $panelEnum = config('accelerator.enums.panel');
        if (! is_string($panelEnum) || ! enum_exists($panelEnum)) {
            return [];
        }

        $registeredPanelIds = array_keys(Filament::getPanels());

        $cases = [];

        foreach ($panelEnum::cases() as $panel) {
            if ($panel instanceof BackedEnum && in_array($panel->value, $registeredPanelIds, true)) {
                $cases[] = $panel;
            }
        }

        return $cases;
    }

    public function getCurrentPanelId(): string
    {
        return Filament::getCurrentPanel()?->getId() ?? '';
    }

    /**
     * @return array<BackedEnum>
     */
    public function getLaunchers(): array
    {
        $launcherEnum = config('accelerator.enums.launcher');
        if (! is_string($launcherEnum) || ! enum_exists($launcherEnum)) {
            return [];
        }

        return array_values(array_filter(
            $launcherEnum::cases(),
            static fn (mixed $launcher): bool => $launcher instanceof BackedEnum,
        ));
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/../../resources/views/livewire/sidebar.blade.php');
    }
}
