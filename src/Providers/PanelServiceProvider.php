<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Support\ServiceProvider;
use Override;
use WireNinja\Accelerator\Providers\Filament\SupportPanelProvider;
use WireNinja\Accelerator\Providers\Filament\SystemPanelProvider;

final class PanelServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->register(SupportPanelProvider::class);
        $this->app->register(SystemPanelProvider::class);
    }
}
