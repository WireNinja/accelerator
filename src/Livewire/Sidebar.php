<?php

namespace WireNinja\Accelerator\Livewire;

use App\Enums\System\PanelEnum;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Livewire\Concerns\HasTenantMenu;
use Filament\Livewire\Concerns\HasUserMenu;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Sidebar extends Component implements HasActions, HasSchemas
{
    use HasTenantMenu;
    use HasUserMenu;
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[On('refresh-sidebar')]
    public function refresh(): void {}

    /**
     * @return array<PanelEnum>
     */
    public function getPanels(): array
    {
        $panelEnum = config('accelerator.enums.panel');
        if (! is_string($panelEnum) || ! enum_exists($panelEnum)) {
            return [];
        }
        /** @var array<PanelEnum> $cases */
        $cases = $panelEnum::cases();

        return $cases;
    }

    public function getCurrentPanelId(): string
    {
        return filament()->getCurrentPanel()?->getId() ?? '';
    }

    /**
     * @return array<mixed>
     */
    public function getLaunchers(): array
    {
        $launcherEnum = config('accelerator.enums.launcher');
        if (! is_string($launcherEnum) || ! enum_exists($launcherEnum)) {
            return [];
        }

        /** @var array<mixed> $cases */
        $cases = $launcherEnum::cases();

        return $cases;
    }

    public function render(): View
    {
        return view('accelerator::livewire.sidebar');
    }
}
