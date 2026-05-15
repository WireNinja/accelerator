<?php

namespace WireNinja\Accelerator;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Override;
use WireNinja\Accelerator\Concerns\InteractsWithApplication;
use WireNinja\Accelerator\Console\Agent\AuditCommand;
use WireNinja\Accelerator\Console\Agent\ModelContextCommand;
use WireNinja\Accelerator\Console\Agent\ResourceContextCommand;
use WireNinja\Accelerator\Console\EnvCommand;
use WireNinja\Accelerator\Console\FlushLastSeenCommand;
use WireNinja\Accelerator\Console\Generator\ModelOutlineCommand;
use WireNinja\Accelerator\Console\Generator\PwaIconsCommand;
use WireNinja\Accelerator\Console\InstallCommand;
use WireNinja\Accelerator\Console\ModelAuditCommand;
use WireNinja\Accelerator\Console\ModelDocCommand;
use WireNinja\Accelerator\Console\NotifyOverdueTicketsCommand;
use WireNinja\Accelerator\Console\Ops\BackupStatusCommand;
use WireNinja\Accelerator\Console\Ops\DeployCommand;
use WireNinja\Accelerator\Console\Ops\EnvCheckCommand;
use WireNinja\Accelerator\Console\Ops\InitEnvCommand;
use WireNinja\Accelerator\Console\Ops\InitServerCommand;
use WireNinja\Accelerator\Console\Ops\LogsCommand;
use WireNinja\Accelerator\Console\Ops\RestartCommand;
use WireNinja\Accelerator\Console\Ops\RollbackCommand;
use WireNinja\Accelerator\Console\Ops\StatusCommand;
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
        $this->trustLocalProxy();

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
                DeployCommand::class,
                EnvCheckCommand::class,
                InitEnvCommand::class,
                InitServerCommand::class,
                LogsCommand::class,
                RestartCommand::class,
                RollbackCommand::class,
                StatusCommand::class,
            ]);
        }

        $this->bootCustomSessionDrivers();
        $this->bootEloquentBestPractices();
        $this->bootApplicationDefaults();
        $this->bootTelegramConfiguration();
        $this->bootShieldDestructiveCommands();
        $this->bootFilamentConfiguration();
    }

    private function trustLocalProxy(): void
    {
        if (! config('accelerator.proxy.trust_local', true)) {
            return;
        }

        TrustProxies::at(['127.0.0.1', '::1']);
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
    }
}
