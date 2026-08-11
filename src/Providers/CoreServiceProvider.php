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
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Nightwatch\Facades\Nightwatch;
use Livewire\LivewireManager;
use NotificationChannels\Telegram\Telegram;
use SessionHandlerInterface;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use WireNinja\Accelerator\Console\Agent\DoctorCommand;
use WireNinja\Accelerator\Console\ConfigureCommand;
use WireNinja\Accelerator\Console\ContextCommand;
use WireNinja\Accelerator\Console\DependenciesCommand;
use WireNinja\Accelerator\Console\Deployment\BackupCleanupCommand;
use WireNinja\Accelerator\Console\Deployment\BackupCommand;
use WireNinja\Accelerator\Console\Deployment\BackupListCommand;
use WireNinja\Accelerator\Console\Deployment\BackupRestoreCommand;
use WireNinja\Accelerator\Console\Deployment\BackupStatusCommand;
use WireNinja\Accelerator\Console\Deployment\BackupVerifyCommand;
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
use WireNinja\Accelerator\Console\Deployment\NotifyTestCommand;
use WireNinja\Accelerator\Console\Deployment\PortsCommand;
use WireNinja\Accelerator\Console\Deployment\RuntimeBackupCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceRestartCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceStartCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceStatusCommand;
use WireNinja\Accelerator\Console\Deployment\ServiceStopCommand;
use WireNinja\Accelerator\Console\EnvCommand;
use WireNinja\Accelerator\Console\FeatureListCommand;
use WireNinja\Accelerator\Console\InstallCommand;
use WireNinja\Accelerator\Console\ProvisionAdminCommand;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Livewire\Synthesizers\BigDecimalSynth;
use WireNinja\Accelerator\Support\OctaneTableSessionHandler;
use WireNinja\Accelerator\Support\Telegram\TelegramBotConfigurator;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->configureBackupFilesystem();

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
        $this->configureBackupSchedule();
        $this->configureEloquent();
        $this->configureApplicationDefaults();
        $this->configureSuperAdminGate();

        if (config('accelerator.features.nightowl', false)) {
            Nightwatch::captureDefaultVendorCommands();
        }

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
            BackupCleanupCommand::class,
            BackupListCommand::class,
            BackupRestoreCommand::class,
            BackupStatusCommand::class,
            BackupVerifyCommand::class,
            RuntimeBackupCommand::class,
            NotifyTestCommand::class,
            EnvCommand::class,
            FeatureListCommand::class,
            InstallCommand::class,
            ProvisionAdminCommand::class,
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
        $config = $this->app['config'];
        $disks = array_values(array_filter((array) $config->get('accelerator.backup.disks', ['local']), is_string(...)));

        if ($config->get('accelerator.backup.s3.enabled', true)) {
            $disks[] = (string) $config->get('accelerator.backup.s3.disk', 'accelerator-s3');
        }

        $disks = array_values(array_unique($disks));
        $config->set('accelerator.backup.disks', $disks);
        $config->set('backup.backup.name', $config->get('accelerator.backup.name', $config->get('app.name')));
        $config->set('backup.backup.source.files.include', $config->get('accelerator.backup.include', [storage_path('app')]));
        $config->set('backup.backup.source.files.relative_path', base_path());
        $config->set('backup.backup.source.files.exclude', array_values(array_unique([
            ...(array) $config->get('backup.backup.source.files.exclude', []),
            storage_path('app/backup-temp'),
            storage_path('framework/accelerator-restore'),
            storage_path('app/private/'.$config->get('accelerator.backup.name')),
            storage_path('app/'.$config->get('accelerator.backup.name')),
        ])));
        $config->set('backup.backup.destination.disks', $disks);
        $config->set('backup.backup.verify_backup', true);
        $nativeNotifications = (array) $config->get('backup.notifications.notifications', []);
        $config->set('backup.notifications.notifications', array_fill_keys(array_keys($nativeNotifications), []));
        $config->set('backup.monitor_backups', [[
            'name' => $config->get('accelerator.backup.name', $config->get('app.name')),
            'disks' => $disks,
            'health_checks' => [
                MaximumAgeInDays::class => $config->get('accelerator.backup.maximum_age_days', 2),
                MaximumStorageInMegabytes::class => $config->get('accelerator.backup.maximum_storage_megabytes', 5000),
            ],
        ]]);

        foreach ((array) $config->get('accelerator.backup.retention', []) as $key => $value) {
            $config->set("backup.cleanup.default_strategy.{$key}", $value);
        }
    }

    private function configureBackupFilesystem(): void
    {
        if (! config('accelerator.backup.s3.enabled', true)) {
            return;
        }

        $deploymentKey = (string) config('accelerator.operations.deployment_key', 'local');
        $stage = (string) config('accelerator.operations.stage', 'local');
        $prefix = trim((string) config('accelerator.backup.s3.prefix', 'accelerator'), '/');
        $root = implode('/', array_filter([$prefix, $deploymentKey, $stage]));
        $disk = (string) config('accelerator.backup.s3.disk', 'accelerator-s3');

        config()->set("filesystems.disks.{$disk}", [
            'driver' => 's3',
            'key' => config('accelerator.backup.s3.access_key_id'),
            'secret' => config('accelerator.backup.s3.secret_access_key'),
            'region' => config('accelerator.backup.s3.region', 'auto'),
            'bucket' => config('accelerator.backup.s3.bucket'),
            'endpoint' => config('accelerator.backup.s3.endpoint'),
            'root' => $root,
            'use_path_style_endpoint' => false,
            'throw' => true,
            'report' => true,
            'visibility' => 'private',
        ]);
    }

    private function configureBackupSchedule(): void
    {
        if (! $this->app->isProduction() || ! config('accelerator.backup.enabled', true)) {
            return;
        }

        $backupTime = (string) config('accelerator.backup.time', '02:00');
        $parsed = CarbonImmutable::createFromFormat('H:i', $backupTime, config('app.timezone'));

        if (! $parsed instanceof CarbonImmutable || $parsed->format('H:i') !== $backupTime) {
            return;
        }

        Schedule::command('accelerator:backup:runtime cleanup --json --no-interaction')
            ->dailyAt($parsed->subHour()->format('H:i'))
            ->withoutOverlapping(360);
        Schedule::command('accelerator:backup:runtime create --only=all --json --no-interaction')
            ->dailyAt($backupTime)
            ->withoutOverlapping(360);
        Schedule::command('accelerator:backup:runtime status --json --no-interaction')
            ->dailyAt($parsed->addHour()->format('H:i'))
            ->withoutOverlapping(360);
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
