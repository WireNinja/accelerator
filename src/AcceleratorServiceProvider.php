<?php

namespace WireNinja\Accelerator;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Override;
use WireNinja\Accelerator\Concerns\InteractsWithApplication;
use WireNinja\Accelerator\Console\AuditCommand;
use WireNinja\Accelerator\Console\EnvCommand;
use WireNinja\Accelerator\Console\FlushLastSeenCommand;
use WireNinja\Accelerator\Console\Generator\ModelOutlineCommand;
use WireNinja\Accelerator\Console\Generator\PwaIconsCommand;
use WireNinja\Accelerator\Console\InstallCommand;
use WireNinja\Accelerator\Console\ModelAuditCommand;
use WireNinja\Accelerator\Console\ModelContextCommand;
use WireNinja\Accelerator\Console\ModelDocCommand;
use WireNinja\Accelerator\Console\NotifyOverdueTicketsCommand;
use WireNinja\Accelerator\Console\Ops\BackupStatusCommand;
use WireNinja\Accelerator\Console\ResourceContextCommand;
use WireNinja\Accelerator\Console\Shield\SafeRegenerateCommand;
use WireNinja\Accelerator\Livewire\Synthesizers\BigDecimalSynth;

class AcceleratorServiceProvider extends ServiceProvider
{
    use InteractsWithApplication;

    #[Override]
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accelerator');
        $this->mergeConfigFrom(__DIR__.'/../config/accelerator.php', 'accelerator');

        FilamentAsset::register([
            Js::make('iconify', 'https://cdn.jsdelivr.net/npm/iconify-icon@2')->loadedOnRequest(),
            Js::make('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js')->loadedOnRequest(),
            Css::make('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css')->loadedOnRequest(),
        ], 'accelerator');

        Livewire::addNamespace(
            namespace: 'accelerator',
            viewPath: __DIR__.'/../resources/views/livewire',
        );

        Livewire::propertySynthesizer(BigDecimalSynth::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCommand::class,
                InstallCommand::class,
                SafeRegenerateCommand::class,
                ModelOutlineCommand::class,
                ModelDocCommand::class,
                ModelAuditCommand::class,
                ModelContextCommand::class,
                ResourceContextCommand::class,
                EnvCommand::class,
                FlushLastSeenCommand::class,
                NotifyOverdueTicketsCommand::class,
                PwaIconsCommand::class,
                BackupStatusCommand::class,
            ]);
        }

        $this->bootCustomSessionDrivers();
        $this->bootEloquentBestPractices();
        $this->bootApplicationDefaults();
        $this->bootTelegramConfiguration();
        $this->bootShieldDestructiveCommands();
        $this->bootFilamentConfiguration();
    }
}
