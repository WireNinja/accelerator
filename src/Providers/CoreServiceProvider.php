<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\LivewireManager;
use NotificationChannels\Telegram\Telegram;
use SessionHandlerInterface;
use WireNinja\Accelerator\Console\Agent\DoctorCommand;
use WireNinja\Accelerator\Console\ConfigureCommand;
use WireNinja\Accelerator\Console\ContextCommand;
use WireNinja\Accelerator\Console\DependenciesCommand;
use WireNinja\Accelerator\Console\Deployment\BackupCommand;
use WireNinja\Accelerator\Console\Deployment\DeployCommand;
use WireNinja\Accelerator\Console\Deployment\DeployInitCommand;
use WireNinja\Accelerator\Console\Deployment\DeployPreflightCommand;
use WireNinja\Accelerator\Console\Deployment\DeployPromoteCommand;
use WireNinja\Accelerator\Console\Deployment\DeployRelocateCommand;
use WireNinja\Accelerator\Console\Deployment\DeployRollbackCommand;
use WireNinja\Accelerator\Console\Deployment\DeployStatusCommand;
use WireNinja\Accelerator\Console\Deployment\DeployUnlockCommand;
use WireNinja\Accelerator\Console\Deployment\EnvironmentDiffCommand;
use WireNinja\Accelerator\Console\Deployment\EnvironmentEditCommand;
use WireNinja\Accelerator\Console\Deployment\EnvironmentPushCommand;
use WireNinja\Accelerator\Console\Deployment\EnvironmentValidateCommand;
use WireNinja\Accelerator\Console\Deployment\LogsCommand;
use WireNinja\Accelerator\Console\Deployment\PortsCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceRestartCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceStartCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceStatusCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceStopCommand;
use WireNinja\Accelerator\Console\EnvCommand;
use WireNinja\Accelerator\Console\FeatureListCommand;
use WireNinja\Accelerator\Console\InstallCommand;
use WireNinja\Accelerator\Console\ProvisionAdminCommand;
use WireNinja\Accelerator\Console\Vps\BackupStatusCommand;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Livewire\Synthesizers\BigDecimalSynth;
use WireNinja\Accelerator\Support\OctaneTableSessionHandler;
use WireNinja\Accelerator\Support\Telegram\TelegramBotConfigurator;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('accelerator.features.telegram')) {
            return;
        }

        $this->app->afterResolving(
            Telegram::class,
            static function (Telegram $telegram, Application $application): void {
                $application->make(TelegramBotConfigurator::class)->configureClient($telegram);
            },
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'accelerator');

        $this->configureTrustedProxy();
        $this->registerCustomSessionDriver();
        $this->configureBackup();
        $this->configureEloquent();
        $this->configureApplicationDefaults();
        $this->configureSuperAdminGate();

        $this->app->make(LivewireManager::class)->propertySynthesizer(BigDecimalSynth::class);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DoctorCommand::class,
            ConfigureCommand::class,
            ContextCommand::class,
            DependenciesCommand::class,
            DeployCommand::class,
            DeployInitCommand::class,
            DeployPreflightCommand::class,
            DeployPromoteCommand::class,
            DeployRelocateCommand::class,
            DeployRollbackCommand::class,
            DeployStatusCommand::class,
            DeployUnlockCommand::class,
            EnvironmentDiffCommand::class,
            EnvironmentEditCommand::class,
            EnvironmentPushCommand::class,
            EnvironmentValidateCommand::class,
            LogsCommand::class,
            PortsCommand::class,
            ServiceRestartCommand::class,
            ServiceStartCommand::class,
            ServiceStatusCommand::class,
            ServiceStopCommand::class,
            BackupCommand::class,
            EnvCommand::class,
            FeatureListCommand::class,
            InstallCommand::class,
            ProvisionAdminCommand::class,
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

    private function registerCustomSessionDriver(): void
    {
        if (config('session.driver') !== 'octane-table') {
            return;
        }

        Session::extend('octane-table', static fn (Application $application): SessionHandlerInterface => new OctaneTableSessionHandler(
            minutes: (int) $application['config']->get('session.lifetime'),
            tableName: (string) $application['config']->get('session.octane_table', 'sessions'),
        ));
    }

    private function configureBackup(): void
    {
        $this->app['config']->set(
            'backup.backup.source.files.include',
            $this->app['config']->get('accelerator.backup.include', [storage_path('app')]),
        );
    }

    /**
     * Filament nested relationship forms rely on global unguarding. Validation
     * and authorization remain the responsibility of schemas, requests, and policies.
     */
    private function configureEloquent(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard();
    }

    private function configureApplicationDefaults(): void
    {
        $isProduction = $this->app->isProduction();

        Date::use(CarbonImmutable::class);
        DB::prohibitDestructiveCommands($isProduction);
        Password::defaults(static fn (): ?Password => $isProduction
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null);
    }

    private function configureSuperAdminGate(): void
    {
        Gate::before(static function (mixed $user): ?bool {
            if (! $user instanceof AcceleratorUser) {
                return null;
            }

            if ($user->isSuspended()) {
                return false;
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }
}
