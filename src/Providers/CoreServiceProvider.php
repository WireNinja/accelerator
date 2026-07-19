<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use WireNinja\Accelerator\Concerns\InteractsWithApplication;
use WireNinja\Accelerator\Console\Agent\DoctorCommand;
use WireNinja\Accelerator\Console\Agent\ModelContextCommand;
use WireNinja\Accelerator\Console\Agent\ResourceContextCommand;
use WireNinja\Accelerator\Console\EnvCommand;
use WireNinja\Accelerator\Console\Generator\ModelOutlineCommand;
use WireNinja\Accelerator\Console\ModelAuditCommand;
use WireNinja\Accelerator\Console\ModelDocCommand;
use WireNinja\Accelerator\Console\Vps\BackupStatusCommand;
use WireNinja\Accelerator\Livewire\Synthesizers\BigDecimalSynth;

final class CoreServiceProvider extends ServiceProvider
{
    use InteractsWithApplication;

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'accelerator');

        $this->configureTrustedProxy();
        $this->bootCustomSessionDrivers();
        $this->bootEloquentBestPractices();
        $this->bootApplicationDefaults();

        if (config('accelerator.features.telegram')) {
            $this->bootTelegramConfiguration();
        }

        Livewire::propertySynthesizer(BigDecimalSynth::class);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DoctorCommand::class,
            ModelOutlineCommand::class,
            ModelDocCommand::class,
            ModelAuditCommand::class,
            ModelContextCommand::class,
            ResourceContextCommand::class,
            EnvCommand::class,
            BackupStatusCommand::class,
        ]);
    }

    private function configureTrustedProxy(): void
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
