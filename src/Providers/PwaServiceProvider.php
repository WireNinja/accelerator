<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;

final class PwaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/pwa.php');

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            static fn (): View => view('accelerator::partials.pwa.head'),
        );
    }
}
