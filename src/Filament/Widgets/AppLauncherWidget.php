<?php

namespace WireNinja\Accelerator\Filament\Widgets;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Widgets\Widget;

class AppLauncherWidget extends Widget
{
    protected string $view = 'accelerator::filament.widgets.app-launcher-widget';

    protected int | string | array $columnSpan = 'full';

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
}
