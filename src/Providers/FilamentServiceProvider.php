<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;
use Spatie\Activitylog\Models\Activity;
use WireNinja\Accelerator\Concerns\InteractsWithApplication;
use WireNinja\Accelerator\Console\Filament\VerifyResourceCommand;
use WireNinja\Accelerator\Console\Shield\SafeRegenerateCommand;
use WireNinja\Accelerator\Policies\ActivityPolicy;

final class FilamentServiceProvider extends ServiceProvider
{
    use InteractsWithApplication;

    public function boot(): void
    {
        $this->registerActivityPolicy();
        $this->registerAssets();
        $this->registerLivewireNamespace();
        $this->bootShieldDestructiveCommands();
        $this->bootFilamentConfiguration();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SafeRegenerateCommand::class,
                VerifyResourceCommand::class,
            ]);
        }
    }

    private function registerActivityPolicy(): void
    {
        if (Gate::getPolicyFor(Activity::class) === null) {
            Gate::policy(Activity::class, ActivityPolicy::class);
        }
    }

    private function registerAssets(): void
    {
        FilamentAsset::register([
            Js::make('iconify', (string) config('accelerator.assets.iconify_url'))->loadedOnRequest(),
            Js::make('leaflet-js', (string) config('accelerator.assets.leaflet_js_url'))->loadedOnRequest(),
            Css::make('leaflet-css', (string) config('accelerator.assets.leaflet_css_url'))->loadedOnRequest(),
        ], package: 'wireninja/accelerator');
    }

    private function registerLivewireNamespace(): void
    {
        $this->app->make(LivewireManager::class)->addNamespace(
            namespace: 'accelerator',
            viewPath: __DIR__.'/../../resources/views/livewire',
        );
    }
}
