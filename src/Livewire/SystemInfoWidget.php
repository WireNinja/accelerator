<?php

namespace WireNinja\Accelerator\Livewire;

use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;
use WireNinja\Accelerator\Filament\Traits\WidgetMustBeLazy;
use WireNinja\Accelerator\Settings\SystemSettings;

class SystemInfoWidget extends Widget
{
    use WidgetMustBeLazy;

    protected int|string|array $columnSpan = 2;

    protected static ?int $sort = -5;

    protected static bool $isLazy = false;

    protected string $view = 'accelerator::livewire.system-info-widget';

    private readonly SystemSettings $appSetting;

    public function __construct()
    {
        $this->appSetting = rescue(fn () => resolve(SystemSettings::class), fn () => new SystemSettings);
    }

    #[Computed()]
    public function getAppSetting(): SystemSettings
    {
        return $this->appSetting;
    }

    #[Computed()]
    public function getAppBrandName(): string
    {
        return $this->getAppSetting()->brand_name;
    }
}
