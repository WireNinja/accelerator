<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Agent;

use BackedEnum;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Laravel\Fortify\FortifyServiceProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;
use WireNinja\Accelerator\AcceleratorServiceProvider;
use WireNinja\Accelerator\Console\Concerns\HasBanner;
use WireNinja\Accelerator\Contracts\AcceleratorUser;

#[Signature('accelerator:doctor {--json : Output as JSON} {--compact : Compact JSON output}')]
#[Description('Verify the Accelerator installation contract and report host warnings')]
final class DoctorCommand extends Command
{
    use HasBanner;

    /**
     * @var list<array{
     *     category: string,
     *     label: string,
     *     value: string,
     *     status: 'ok'|'warning'|'error',
     *     message: string|null
     * }>
     */
    private array $checks = [];

    public function __construct()
    {
        parent::__construct();

        $this->setAliases(['agent:audit']);
    }

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        $this->inspectRuntime();
        $this->inspectRecipe();
        $this->inspectConfiguration();
        $this->inspectDatabase();
        $this->inspectHost();

        $errors = $this->messagesFor('error');
        $warnings = $this->messagesFor('warning');

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'status' => $errors !== [] ? 'ERROR' : ($warnings !== [] ? 'WARNING' : 'OK'),
                'errors' => $errors,
                'warnings' => $warnings,
                'checks' => $this->checks,
            ], ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->displayBanner();

        foreach ($this->groupedChecks() as $category => $checks) {
            $this->components->info($category);
            $this->table(
                ['Check', 'Current', 'Status'],
                array_map(fn (array $check): array => [
                    $check['label'],
                    $check['value'],
                    $this->statusLabel($check['status']),
                ], $checks),
            );
            $this->newLine();
        }

        if ($errors !== []) {
            $this->components->error('Installation contract failed.');
            $this->components->bulletList($errors);
        } elseif ($warnings !== []) {
            $this->components->warn('Accelerator is installed; review the host warnings before production.');
            $this->components->bulletList($warnings);
        } else {
            $this->components->success('Accelerator is installed and ready.');
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function inspectRuntime(): void
    {
        $this->assert(
            category: 'Runtime',
            label: 'PHP',
            value: PHP_VERSION,
            passed: version_compare(PHP_VERSION, '8.5.0', '>='),
            message: 'Accelerator v2 requires PHP 8.5 or newer.',
        );
        $this->assert(
            category: 'Runtime',
            label: 'Laravel',
            value: Application::VERSION,
            passed: str_starts_with(Application::VERSION, '13.'),
            message: 'Accelerator v2 currently supports Laravel 13 only.',
        );
        $this->assert(
            category: 'Runtime',
            label: 'Package discovery',
            value: AcceleratorServiceProvider::class,
            passed: app()->getProvider(AcceleratorServiceProvider::class) !== null,
            message: 'Composer did not discover AcceleratorServiceProvider.',
        );
    }

    private function inspectRecipe(): void
    {
        $requiredFiles = [
            'app/Enums/System/PanelEnum.php',
            'app/Enums/System/ResourceEnum.php',
            'app/Enums/System/RoleEnum.php',
            'app/Models/User.php',
            'app/Support/helpers.php',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'database/seeders/DatabaseSeeder.php',
            'public/.user.ini',
            'public/favicon.svg',
            'phpstan.neon',
            'rector.php',
            'resources/css/app.css',
            'resources/css/filament/theme.css',
            'resources/js/app.ts',
            'resources/svg/.gitkeep',
            'resources/views/app.blade.php',
            'routes/channels.php',
            'routes/console.php',
            'routes/web.php',
            'tsconfig.json',
            'vite.config.js',
            'bun.lock',
            'composer.lock',
            'public/build/manifest.json',
        ];

        $missingFiles = array_values(array_filter(
            $requiredFiles,
            fn (string $path): bool => ! is_file(base_path($path)),
        ));
        $this->assert(
            category: 'Install recipe',
            label: 'Required files',
            value: $missingFiles === [] ? count($requiredFiles).' present' : implode(', ', $missingFiles),
            passed: $missingFiles === [],
            message: 'Required application files are missing: '.implode(', ', $missingFiles),
        );

        $configuredUserClass = config('auth.providers.users.model');
        $userClass = is_string($configuredUserClass) ? $configuredUserClass : '[not configured]';
        $compatibleUser = class_exists($userClass)
            && is_subclass_of($userClass, Model::class)
            && is_subclass_of($userClass, AcceleratorUser::class);
        $this->assert(
            category: 'Install recipe',
            label: 'User model',
            value: $userClass,
            passed: $compatibleUser,
            message: 'The configured auth user model must extend Eloquent Model and implement '.AcceleratorUser::class.'.',
        );

        $bootstrap = $this->contents('bootstrap/app.php');
        $this->assert(
            category: 'Install recipe',
            label: 'HTTP bootstrap',
            value: 'middleware + exceptions',
            passed: str_contains($bootstrap, 'BuiltinMiddleware::make') && str_contains($bootstrap, 'BuiltinExceptions::make'),
            message: 'bootstrap/app.php is missing the Accelerator middleware or exception integration.',
        );

        $providers = $this->contents('bootstrap/providers.php');
        $this->assert(
            category: 'Install recipe',
            label: 'Application provider',
            value: 'AppServiceProvider',
            passed: str_contains($providers, 'AppServiceProvider::class'),
            message: 'AppServiceProvider is not registered in bootstrap/providers.php.',
        );

        $vite = $this->contents('vite.config.js');
        $this->assert(
            category: 'Install recipe',
            label: 'Theme discovery',
            value: 'resources/css/filament/**/theme.css',
            passed: str_contains($vite, 'discoverFilamentThemes'),
            message: 'vite.config.js is not configured for automatic Filament theme discovery.',
        );
        $this->assert(
            category: 'Install recipe',
            label: 'Storage link',
            value: public_path('storage'),
            passed: is_link(public_path('storage')),
            message: 'public/storage is not a symlink; run php artisan storage:link.',
        );

        if (! config('accelerator.features.filament', false)) {
            return;
        }

        $panelProvider = 'app/Providers/Filament/AdminPanelProvider.php';
        $this->assert(
            category: 'Install recipe',
            label: 'Filament panel',
            value: $panelProvider,
            passed: is_file(base_path($panelProvider))
                && str_contains($this->contents($panelProvider), 'PanelPreset::configure'),
            message: 'Filament is enabled but AdminPanelProvider does not use PanelPreset.',
        );
        $this->assert(
            category: 'Install recipe',
            label: 'Panel provider registration',
            value: 'AdminPanelProvider',
            passed: str_contains($providers, 'AdminPanelProvider::class'),
            message: 'Filament is enabled but AdminPanelProvider is not registered.',
        );
    }

    private function inspectConfiguration(): void
    {
        $featureNames = [
            'filament',
            'fortify',
            'panels',
            'oauth',
            'insider',
            'pwa',
            'settings',
            'telegram',
            'telemetry',
            'ticketing',
        ];
        $missingFeatureKeys = [];

        foreach (['.env', '.env.example'] as $environmentFile) {
            $contents = $this->contents($environmentFile);

            foreach ($featureNames as $featureName) {
                $key = 'ACCELERATOR_FEATURE_'.strtoupper($featureName);

                if (preg_match('/^'.preg_quote($key, '/').'=(?:true|false)$/m', $contents) !== 1) {
                    $missingFeatureKeys[] = "{$environmentFile}:{$key}";
                }
            }
        }

        $this->assert(
            category: 'Configuration',
            label: 'Feature switches',
            value: $missingFeatureKeys === [] ? count($featureNames).' explicit booleans' : implode(', ', $missingFeatureKeys),
            passed: $missingFeatureKeys === [],
            message: 'Feature switches must be explicit true/false values in .env and .env.example.',
        );

        $appKey = (string) config('app.key');
        $this->assert(
            category: 'Configuration',
            label: 'Application key',
            value: $appKey === '' ? 'empty' : 'set',
            passed: $appKey !== '',
            message: 'APP_KEY is empty.',
        );

        $filamentEnabled = (bool) config('accelerator.features.filament', false);
        $fortifyEnabled = (bool) config('accelerator.features.fortify', false);
        $panelsEnabled = (bool) config('accelerator.features.panels', false);
        $fortifyLoaded = app()->getProvider(FortifyServiceProvider::class) !== null;
        $fortifyRouteCount = collect(app('router')->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => str_contains($route->getActionName(), 'Laravel\\Fortify'))
            ->count();
        $this->assert(
            category: 'Configuration',
            label: 'Fortify activation',
            value: sprintf('%s, %d routes', $fortifyLoaded ? 'loaded' : 'not loaded', $fortifyRouteCount),
            passed: $fortifyEnabled === $fortifyLoaded && $fortifyRouteCount === ($fortifyEnabled ? 4 : 0),
            message: 'Fortify provider/routes do not match ACCELERATOR_FEATURE_FORTIFY; rebuild config and route caches.',
        );
        $this->assert(
            category: 'Configuration',
            label: 'Panel dependency',
            value: $panelsEnabled ? 'enabled' : 'disabled',
            passed: ! $panelsEnabled || $filamentEnabled,
            message: 'ACCELERATOR_FEATURE_PANELS requires ACCELERATOR_FEATURE_FILAMENT=true.',
        );

        $oauthEnabled = (bool) config('accelerator.features.oauth', false);
        $this->assert(
            category: 'Configuration',
            label: 'OAuth dependency',
            value: $oauthEnabled ? 'enabled' : 'disabled',
            passed: ! $oauthEnabled || $filamentEnabled,
            message: 'ACCELERATOR_FEATURE_OAUTH requires ACCELERATOR_FEATURE_FILAMENT=true.',
        );
        $oauthMode = (string) config('accelerator.oauth.mode', 'disabled');
        $configuredOauthDomains = config('accelerator.oauth.allowed_domains', []);
        $oauthDomains = is_array($configuredOauthDomains)
            ? array_filter(
                $configuredOauthDomains,
                fn (mixed $domain): bool => is_string($domain) && (trim($domain) !== ''),
            )
            : [];
        $oauthValid = (! $oauthEnabled)
            || ($oauthMode === 'existing_only')
            || (($oauthMode === 'allowed_domains') && ($oauthDomains !== []));
        $this->assert(
            category: 'Configuration',
            label: 'OAuth mode',
            value: $oauthMode,
            passed: $oauthValid,
            message: 'Enabled OAuth requires existing_only, or allowed_domains with ACCELERATOR_OAUTH_ALLOWED_DOMAINS.',
        );
        $oauthCredentialsReady = filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
        $this->assert(
            category: 'Configuration',
            label: 'Google OAuth credentials',
            value: $oauthCredentialsReady ? 'configured' : 'not configured',
            passed: ! $oauthEnabled || $oauthCredentialsReady,
            message: 'OAuth is selected but GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are CONFIG_REQUIRED.',
            failureStatus: 'warning',
        );
        $oauthRouteCount = collect(app('router')->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => is_string($route->getName()) && str_starts_with($route->getName(), 'auth.google.'))
            ->count();
        $this->assert(
            category: 'Configuration',
            label: 'Google OAuth routes',
            value: (string) $oauthRouteCount,
            passed: $oauthRouteCount === (($oauthEnabled && $filamentEnabled && $oauthCredentialsReady) ? 2 : 0),
            message: 'Google OAuth route topology is stale; rebuild config and route caches.',
        );

        $uploadMegabytes = (int) config('accelerator.uploads.max_megabytes', 100);
        $this->assert(
            category: 'Configuration',
            label: 'Upload policy',
            value: "{$uploadMegabytes} MB",
            passed: $uploadMegabytes > 0,
            message: 'ACCELERATOR_UPLOAD_MAX_MB must be greater than zero.',
        );
    }

    private function inspectDatabase(): void
    {
        try {
            $tables = ['migrations', 'users', 'cache', 'jobs', 'roles', 'permissions'];
            $missingTables = array_values(array_filter(
                $tables,
                fn (string $table): bool => ! Schema::hasTable($table),
            ));

            $this->assert(
                category: 'Database',
                label: 'Canonical schema',
                value: $missingTables === [] ? count($tables).' core tables present' : implode(', ', $missingTables),
                passed: $missingTables === [],
                message: 'Database schema is incomplete: '.implode(', ', $missingTables),
            );

            if ($missingTables !== []) {
                return;
            }

            $roleEnum = config('accelerator.enums.role');
            $expectedRoles = is_string($roleEnum) && enum_exists($roleEnum)
                ? array_values(array_filter(array_map(
                    static fn (mixed $case): ?string => $case instanceof BackedEnum && is_string($case->value)
                        ? $case->value
                        : null,
                    $roleEnum::cases(),
                )))
                : [];
            $existingRoles = Role::query()->whereIn('name', $expectedRoles)->pluck('name')->all();
            $missingRoles = array_values(array_diff($expectedRoles, $existingRoles));
            $this->assert(
                category: 'Database',
                label: 'Application roles',
                value: $missingRoles === [] ? count($expectedRoles).' synchronized' : implode(', ', $missingRoles),
                passed: $expectedRoles !== [] && $missingRoles === [],
                message: 'RoleEnum is not synchronized; run php artisan shield:safe-regenerate.',
            );

            $superAdminRole = (string) config('filament-shield.super_admin.name', 'super_admin');
            $superAdmin = Role::findByName($superAdminRole);
            $activeSuperAdmins = $superAdmin->users()->whereNull('suspended_at')->count();
            $this->assert(
                category: 'Database',
                label: 'Active Super Admin',
                value: (string) $activeSuperAdmins,
                passed: $activeSuperAdmins > 0,
                message: 'No active Super Admin account is provisioned.',
            );
        } catch (Throwable $throwable) {
            $this->record(
                category: 'Database',
                label: 'Connection',
                value: (string) config('database.default'),
                status: 'error',
                message: 'Database is unavailable: '.$throwable->getMessage(),
            );
        }
    }

    private function inspectHost(): void
    {
        $finder = new ExecutableFinder;

        foreach (['composer', 'bun', 'git'] as $executable) {
            $path = $finder->find($executable);
            $this->assert(
                category: 'Host readiness',
                label: ucfirst($executable),
                value: $path ?? 'not found',
                passed: $path !== null,
                message: "{$executable} is not available on PATH.",
                failureStatus: 'warning',
            );
        }

        $requiredMegabytes = (int) config('accelerator.uploads.max_megabytes', 100);
        $requiredPostMegabytes = max($requiredMegabytes + 10, intdiv(($requiredMegabytes * 11) + 9, 10));

        foreach ([
            'upload_max_filesize' => $requiredMegabytes,
            'post_max_size' => $requiredPostMegabytes,
        ] as $setting => $minimumMegabytes) {
            $value = (string) ini_get($setting);
            $this->assert(
                category: 'Host readiness',
                label: $setting,
                value: $value,
                passed: $this->bytes($value) >= ($minimumMegabytes * 1024 * 1024),
                message: "PHP {$setting} is {$value}; configure the web runtime for at least {$minimumMegabytes} MB.",
                failureStatus: 'warning',
            );
        }

        $opcacheEnabled = function_exists('opcache_get_status') && opcache_get_status(false) !== false;
        $this->assert(
            category: 'Host readiness',
            label: 'OPcache',
            value: $opcacheEnabled ? 'enabled' : 'disabled for '.PHP_SAPI,
            passed: $opcacheEnabled,
            message: 'OPcache is disabled for this PHP SAPI; verify the production web runtime separately.',
            failureStatus: 'warning',
        );
    }

    /**
     * @param  'warning'|'error'  $failureStatus
     */
    private function assert(
        string $category,
        string $label,
        string $value,
        bool $passed,
        string $message,
        string $failureStatus = 'error',
    ): void {
        $this->record($category, $label, $value, $passed ? 'ok' : $failureStatus, $passed ? null : $message);
    }

    /**
     * @param  'ok'|'warning'|'error'  $status
     */
    private function record(string $category, string $label, string $value, string $status, ?string $message): void
    {
        $this->checks[] = compact('category', 'label', 'value', 'status', 'message');
    }

    private function contents(string $path): string
    {
        $contents = @file_get_contents(base_path($path));

        return is_string($contents) ? $contents : '';
    }

    /**
     * @return list<string>
     */
    private function messagesFor(string $status): array
    {
        return array_values(array_map(
            fn (array $check): string => $check['message'] ?? $check['label'],
            array_filter($this->checks, fn (array $check): bool => $check['status'] === $status),
        ));
    }

    /**
     * @return array<string, list<array{
     *     category: string,
     *     label: string,
     *     value: string,
     *     status: 'ok'|'warning'|'error',
     *     message: string|null
     * }>>
     */
    private function groupedChecks(): array
    {
        $groups = [];

        foreach ($this->checks as $check) {
            $groups[$check['category']][] = $check;
        }

        return $groups;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'ok' => '<fg=green>OK</>',
            'warning' => '<fg=yellow>WARNING</>',
            default => '<fg=red>ERROR</>',
        };
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1') {
            return PHP_INT_MAX;
        }

        if ($value === '') {
            return 0;
        }

        $bytes = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }
}
