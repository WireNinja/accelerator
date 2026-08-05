<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;

final class HeadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            static fn (): View => view()->file(__DIR__.'/../../resources/views/partials/head.blade.php'),
        );
    }
}
