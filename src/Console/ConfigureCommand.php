<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Configuration\EnvironmentStore;
use WireNinja\Accelerator\Configuration\SshConfig;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Support\UserModel;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class ConfigureCommand extends Command
{
    /** @var string */
    protected $signature = 'accelerator:configure
        {scope? : application, features, deployment, or environment}
        {--stage= : staging or production}';

    /** @var string */
    protected $description = 'Configure Accelerator locally through validated environment files';

    /** @var array<string, string> */
    private const FEATURE_KEYS = [
        'fortify' => 'ACCELERATOR_FEATURE_FORTIFY',
        'settings' => 'ACCELERATOR_FEATURE_SETTINGS',
        'ticketing' => 'ACCELERATOR_FEATURE_TICKETING',
        'oauth' => 'ACCELERATOR_FEATURE_OAUTH',
        'pwa' => 'ACCELERATOR_FEATURE_PWA',
        'telegram' => 'ACCELERATOR_FEATURE_TELEGRAM',
        'telemetry' => 'ACCELERATOR_FEATURE_TELEMETRY',
        'insider' => 'ACCELERATOR_FEATURE_INSIDER',
        'horizon' => 'ACCELERATOR_FEATURE_HORIZON',
        'reverb' => 'BROADCAST_CONNECTION',
        'scout' => 'SCOUT_DRIVER',
        'nightwatch' => 'NIGHTWATCH_ENABLED',
    ];

    /** @var list<string> */
    private const SENSITIVE_KEY_PARTS = ['KEY', 'PASSWORD', 'SECRET', 'TOKEN', 'WEBHOOK'];

    public function handle(): int
    {
        $scope = (string) ($this->argument('scope') ?: select(
            label: 'Configuration section',
            options: [
                'application' => 'Application identity and local URL',
                'features' => 'Runtime feature activation',
                'deployment' => 'VPS topology and Envoy',
                'environment' => 'Stage Laravel environment',
            ],
        ));

        if (! in_array($scope, ['application', 'features', 'deployment', 'environment'], true)) {
            $this->components->error("Unknown configuration section [{$scope}].");

            return self::FAILURE;
        }

        $store = new EnvironmentStore($this->laravel->basePath());

        try {
            return match ($scope) {
                'application' => $this->configureApplication($store),
                'features' => $this->configureFeatures($store),
                'deployment' => $this->configureDeployment($store),
                'environment' => $this->configureEnvironment($store),
            };
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function configureApplication(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $draft = [
            'APP_NAME' => text(label: 'Application name', default: $current['APP_NAME'] ?? config('app.name'), required: true),
            'APP_URL' => text(
                label: 'Local application URL',
                default: $current['APP_URL'] ?? 'http://localhost:8000',
                required: true,
                validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL) ? null : 'Enter an absolute URL.',
            ),
        ];
        $draft['VITE_APP_NAME'] = $draft['APP_NAME'];
        $draft['GOOGLE_REDIRECT_URI'] = rtrim($draft['APP_URL'], '/').'/auth/google/callback';

        note('Affected files: .env, .env.example');

        if (! $this->confirmDraft('.env', $current, $draft)) {
            return self::SUCCESS;
        }

        $store->merge('.env', $draft);
        $store->merge('.env.example', $draft);
        $this->refreshCaches();
        $this->components->info('Application configuration updated. Next: php artisan serve');

        return self::SUCCESS;
    }

    private function configureFeatures(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $selected = multiselect(
            label: 'Active optional runtime features',
            options: array_combine(array_keys(self::FEATURE_KEYS), array_keys(self::FEATURE_KEYS)),
            default: array_keys(array_filter(
                self::FEATURE_KEYS,
                static fn (string $environmentKey, string $feature): bool => self::featureEnabled(
                    $feature,
                    $current[$environmentKey] ?? '',
                ),
                ARRAY_FILTER_USE_BOTH,
            )),
            required: false,
            hint: 'Admin and System panels are Filament core. Support appears only with ticketing.',
        );
        $draft = ['ACCELERATOR_FEATURE_FILAMENT' => 'true'];

        foreach (self::FEATURE_KEYS as $feature => $key) {
            $enabled = in_array($feature, $selected, true);
            $draft[$key] = match ($feature) {
                'reverb' => $enabled ? 'reverb' : 'log',
                'scout' => $enabled ? 'database' : 'collection',
                default => $enabled ? 'true' : 'false',
            };

            if ($feature === 'oauth') {
                $draft['ACCELERATOR_OAUTH_MODE'] = $enabled ? ($current['ACCELERATOR_OAUTH_MODE'] ?? 'existing_only') : 'disabled';
            }
        }

        note('Affected files: .env, .env.example');

        if (! $this->confirmDraft('.env', $current, $draft)) {
            return self::SUCCESS;
        }

        $store->merge('.env', $draft);
        $store->merge('.env.example', $draft);
        $this->refreshCaches();
        $this->components->info('Feature configuration updated. Next: php artisan accelerator:doctor');

        return self::SUCCESS;
    }

    private function configureDeployment(EnvironmentStore $store): int
    {
        DeploymentConfig::assertNoLegacyFiles($this->laravel->basePath());
        $current = $store->read('.accelerator/deploy.env');
        $local = $store->read('.env');
        $aliases = SshConfig::aliases();
        $currentHost = $current['OPS_DEPLOY_SSH_HOST'] ?? '';
        $sshHost = $aliases === []
            ? text(label: 'SSH host alias from ~/.ssh/config', default: $currentHost, required: true)
            : select(
                label: 'SSH host alias',
                options: array_combine($aliases, $aliases),
                default: in_array($currentHost, $aliases, true) ? $currentHost : null,
            );
        $topology = select(
            label: 'Deployment topology',
            options: ['single' => 'Production only', 'dual' => 'Staging and production'],
            default: self::truthy($current['OPS_DEPLOY_STAGING_ENABLED'] ?? 'false') ? 'dual' : 'single',
        );
        $project = text(label: 'Project key', default: $current['OPS_DEPLOY_PROJECT'] ?? basename($this->laravel->basePath()), required: true);
        $repository = text(label: 'Git repository URL', default: $current['OPS_DEPLOY_REPO'] ?? $this->gitValue(['remote', 'get-url', 'origin']), required: true);
        $branch = text(label: 'Deployment branch', default: $current['OPS_DEPLOY_BRANCH'] ?? ($this->gitValue(['branch', '--show-current']) ?: 'main'), required: true);
        $sslEmail = text(
            label: 'SSL notification email',
            default: $current['OPS_DEPLOY_SSL_EMAIL'] ?? ($local['MAIL_FROM_ADDRESS'] ?? ''),
            required: true,
            validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.',
        );
        $productionDomain = text(label: 'Production domain', default: $current['OPS_DEPLOY_PRODUCTION_DOMAIN'] ?? '', required: true);
        $productionRoot = text(label: 'Production root', default: $current['OPS_DEPLOY_PRODUCTION_ROOT'] ?? "/var/www/{$project}", required: true);
        $stagingDomain = '';
        $stagingRoot = '';

        if ($topology === 'dual') {
            $stagingDomain = text(label: 'Staging domain', default: $current['OPS_DEPLOY_STAGING_DOMAIN'] ?? "staging.{$productionDomain}", required: true);
            $stagingRoot = text(label: 'Staging root', default: $current['OPS_DEPLOY_STAGING_ROOT'] ?? "/var/www/{$project}-staging", required: true);
        }

        $runtime = select(
            label: 'HTTP runtime',
            options: ['octane' => 'Octane + Swoole', 'fpm' => 'PHP-FPM'],
            default: $current['OPS_DEPLOY_PRODUCTION_HTTP_RUNTIME'] ?? 'octane',
        );
        $initialAdmin = confirm('Provision an existing Super Admin during first init?', default: true)
            ? $this->initialAdmin()
            : null;

        DeploymentConfig::validateInstallerTargets(
            project: $project,
            sshHost: $sshHost,
            repository: $repository,
            branch: $branch,
            productionDomain: $productionDomain,
            productionRoot: $productionRoot,
            stagingDomain: $topology === 'dual' ? $stagingDomain : null,
            stagingRoot: $topology === 'dual' ? $stagingRoot : null,
        );

        $contents = $this->renderDeploymentTemplate([
            '{{ default_stage }}' => $topology === 'dual' ? 'staging' : 'production',
            '{{ project }}' => $project,
            '{{ ssh_host }}' => $sshHost,
            '{{ repo }}' => $repository,
            '{{ branch }}' => $branch,
            '{{ ssl_email }}' => $sslEmail,
            '{{ admin_name }}' => $initialAdmin['name'] ?? '',
            '{{ admin_username }}' => $initialAdmin['username'] ?? '',
            '{{ admin_email }}' => $initialAdmin['email'] ?? '',
            '{{ admin_password_hash }}' => $initialAdmin['password_hash'] ?? '',
            '{{ staging_enabled }}' => $topology === 'dual' ? 'true' : 'false',
            '{{ staging_domain }}' => $stagingDomain,
            '{{ staging_root }}' => $stagingRoot,
            '{{ production_domain }}' => $productionDomain,
            '{{ production_root }}' => $productionRoot,
            '{{ http_runtime }}' => $runtime,
            '{{ horizon_enabled }}' => self::boolean($local['ACCELERATOR_FEATURE_HORIZON'] ?? 'false'),
            '{{ queue_worker_enabled }}' => self::boolean(! self::truthy($local['ACCELERATOR_FEATURE_HORIZON'] ?? 'false')),
            '{{ queue_connection }}' => $local['QUEUE_CONNECTION'] ?? 'database',
            '{{ reverb_enabled }}' => self::boolean(($local['BROADCAST_CONNECTION'] ?? 'log') === 'reverb'),
            '{{ nightwatch_enabled }}' => self::boolean($local['NIGHTWATCH_ENABLED'] ?? 'false'),
        ]);
        $contents = str_replace(
            'OPS_DEPLOY_INITIAL_ADMIN_ENABLED=true',
            'OPS_DEPLOY_INITIAL_ADMIN_ENABLED='.($initialAdmin === null ? 'false' : 'true'),
            $contents,
        );
        $draft = $this->parseContents($contents);
        $this->validateDeploymentDraft($contents, $topology);
        note('Stage files affected: .accelerator/environments/production.env'.($topology === 'dual'
            ? ', .accelerator/environments/staging.env'
            : ''));

        if (! $this->confirmDraft('.accelerator/deploy.env', $current, $draft)) {
            return self::SUCCESS;
        }

        $store->write('.accelerator/deploy.env', $contents);
        $this->ensureStageEnvironment($store, 'production', $productionDomain, $productionRoot, $local);

        if ($topology === 'dual') {
            $this->ensureStageEnvironment($store, 'staging', $stagingDomain, $stagingRoot, $local);
        }

        $firstStage = $topology === 'dual' ? 'staging' : 'production';
        $this->components->info("Deployment configuration updated. Next: php artisan accelerator:configure environment --stage={$firstStage}");

        return self::SUCCESS;
    }

    private function configureEnvironment(EnvironmentStore $store): int
    {
        DeploymentConfig::assertNoLegacyFiles($this->laravel->basePath());
        $deployment = $store->read('.accelerator/deploy.env');

        if ($deployment === []) {
            throw new RuntimeException('Deployment is not configured. Run: php artisan accelerator:configure deployment');
        }

        $stage = (string) ($this->option('stage') ?: select(
            label: 'Environment stage',
            options: array_filter([
                self::truthy($deployment['OPS_DEPLOY_STAGING_ENABLED'] ?? 'false') ? 'staging' : null,
                'production',
            ]),
        ));

        if (! in_array($stage, ['staging', 'production'], true)) {
            throw new RuntimeException('Stage must be staging or production.');
        }

        $prefix = 'OPS_DEPLOY_'.strtoupper($stage).'_';

        if (! self::truthy($deployment[$prefix.'ENABLED'] ?? 'false')) {
            throw new RuntimeException("Deployment stage [{$stage}] is disabled.");
        }

        $path = ".accelerator/environments/{$stage}.env";
        $current = $store->read($path);
        $draft = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://'.$deployment[$prefix.'DOMAIN'],
            'LOG_LEVEL' => 'error',
        ];
        $database = $current['DB_CONNECTION'] ?? 'sqlite';

        if ($database === 'sqlite') {
            $draft['DB_DATABASE'] = rtrim($deployment[$prefix.'ROOT'], '/').'/shared/database/database.sqlite';
        } else {
            $draft['DB_HOST'] = text(label: strtoupper($stage).' database host', default: $current['DB_HOST'] ?? '127.0.0.1', required: true);
            $draft['DB_PORT'] = text(label: strtoupper($stage).' database port', default: $current['DB_PORT'] ?? ($database === 'pgsql' ? '5432' : '3306'), required: true);
            $draft['DB_DATABASE'] = text(label: strtoupper($stage).' database name', default: $current['DB_DATABASE'] ?? '', required: true);
            $draft['DB_USERNAME'] = text(label: strtoupper($stage).' database user', default: $current['DB_USERNAME'] ?? '', required: true);
            $draft['DB_PASSWORD'] = text(label: strtoupper($stage).' database password', default: $current['DB_PASSWORD'] ?? '', required: true);
        }

        if (($current['APP_KEY'] ?? '') === '') {
            $draft['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        if (! $this->confirmDraft($path, $current, $draft)) {
            return self::SUCCESS;
        }

        $store->merge($path, $draft);
        $missing = $this->missingExternalCredentials([...$current, ...$draft]);

        if ($missing !== []) {
            note('Stage saved but external credentials are incomplete: '.implode(', ', $missing));
            $this->components->warn("Next: complete {$path}, then rerun this command.");

            return self::SUCCESS;
        }

        $this->components->info("Stage is ready. Next: vendor/bin/envoy run init --stage={$stage}");

        return self::SUCCESS;
    }

    /** @param array<string, string> $current @param array<string, string> $draft */
    private function confirmDraft(string $path, array $current, array $draft): bool
    {
        $rows = [];

        foreach ($draft as $key => $value) {
            if (($current[$key] ?? null) === $value) {
                continue;
            }

            $rows[] = [$key, $this->displayValue($key, $current[$key] ?? ''), $this->displayValue($key, $value)];
        }

        if ($rows === []) {
            note("No changes for {$path}.");

            return false;
        }

        $this->table(['Key', 'Current', 'Draft'], $rows);
        note("Affected file: {$path}");

        return confirm('Write this configuration?', default: false);
    }

    /** @param array<string, string> $replacements */
    private function renderDeploymentTemplate(array $replacements): string
    {
        $path = dirname(__DIR__, 2).'/.base-env.envoy.example';
        $template = file_get_contents($path);

        if (! is_string($template)) {
            throw new RuntimeException('Unable to read deployment configuration template.');
        }

        return strtr($template, $replacements);
    }

    private function validateDeploymentDraft(string $contents, string $topology): void
    {
        $root = sys_get_temp_dir().'/accelerator-config-'.bin2hex(random_bytes(8));
        $directory = $root.'/.accelerator';

        if (! mkdir($directory, 0700, true) || file_put_contents($directory.'/deploy.env', $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to validate deployment draft.');
        }

        try {
            DeploymentConfig::load($root, 'production');

            if ($topology === 'dual') {
                DeploymentConfig::load($root, 'staging');
            }
        } finally {
            unlink($directory.'/deploy.env');
            rmdir($directory);
            rmdir($root);
        }
    }

    /** @param array<string, string> $local */
    private function ensureStageEnvironment(EnvironmentStore $store, string $stage, string $domain, string $root, array $local): void
    {
        $path = ".accelerator/environments/{$stage}.env";

        if ($store->read($path) !== []) {
            return;
        }

        $draft = $local;
        $draft['APP_ENV'] = 'production';
        $draft['APP_DEBUG'] = 'false';
        $draft['APP_URL'] = "https://{$domain}";
        $draft['LOG_LEVEL'] = 'error';
        $draft['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));

        foreach (['DB_PASSWORD', 'GOOGLE_CLIENT_SECRET', 'NIGHTWATCH_TOKEN', 'TELEGRAM_BOT_TOKEN', 'VAPID_PRIVATE_KEY'] as $key) {
            $draft[$key] = '';
        }

        if (($draft['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
            $draft['DB_DATABASE'] = rtrim($root, '/').'/shared/database/database.sqlite';
        } else {
            $draft['DB_DATABASE'] = '';
            $draft['DB_USERNAME'] = '';
            $draft['DB_PASSWORD'] = '';
        }

        if (($draft['BROADCAST_CONNECTION'] ?? 'log') === 'reverb') {
            $key = bin2hex(random_bytes(16));
            $draft['REVERB_APP_ID'] = bin2hex(random_bytes(8));
            $draft['REVERB_APP_KEY'] = $key;
            $draft['REVERB_APP_SECRET'] = bin2hex(random_bytes(32));
            $draft['VITE_REVERB_APP_KEY'] = $key;
        }

        $store->replace($path, $draft);
    }

    /** @return array<string, string> */
    private function parseContents(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#') && str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim($value);
            }
        }

        return $values;
    }

    /** @param array<string, string> $values @return list<string> */
    private function missingExternalCredentials(array $values): array
    {
        $required = match ($values['DB_CONNECTION'] ?? 'sqlite') {
            'mysql', 'pgsql' => ['DB_PASSWORD'],
            default => [],
        };

        if (self::truthy($values['ACCELERATOR_FEATURE_OAUTH'] ?? 'false')) {
            $required[] = 'GOOGLE_CLIENT_ID';
            $required[] = 'GOOGLE_CLIENT_SECRET';
        }

        if (self::truthy($values['NIGHTWATCH_ENABLED'] ?? 'false')) {
            $required[] = 'NIGHTWATCH_TOKEN';
        }

        return array_values(array_filter($required, static fn (string $key): bool => ($values[$key] ?? '') === ''));
    }

    private function refreshCaches(): void
    {
        $configCached = is_file($this->laravel->bootstrapPath('cache/config.php'));
        $routeCached = glob($this->laravel->bootstrapPath('cache/routes-*.php')) !== [];
        $this->callSilent('config:clear');
        $this->callSilent('route:clear');

        if ($configCached) {
            $this->callSilent('config:cache');
        }

        if ($routeCached) {
            $this->callSilent('route:cache');
        }

        note($configCached || $routeCached ? 'Existing local caches were rebuilt.' : 'Local configuration and route caches were clear.');
    }

    /** @return array{name: string, username: string, email: string, password_hash: string} */
    private function initialAdmin(): array
    {
        $role = (string) config('filament-shield.super_admin.name', 'super_admin');
        $admins = UserModel::query()
            ->whereNull('suspended_at')
            ->whereHas('roles', static fn ($query) => $query->where('name', $role))
            ->get()
            ->filter(static fn (mixed $user): bool => $user instanceof AcceleratorUser)
            ->values();

        if ($admins->isEmpty()) {
            throw new RuntimeException('No active Super Admin exists. Run accelerator:provision-admin first.');
        }

        $admin = $admins->count() === 1
            ? $admins->first()
            : $admins->firstWhere(
                'email',
                select(
                    label: 'Initial remote Super Admin',
                    options: $admins->mapWithKeys(static fn ($user): array => [
                        (string) $user->getAttribute('email') => (string) $user->getAttribute('email'),
                    ])->all(),
                ),
            );

        if (! $admin instanceof AcceleratorUser) {
            throw new RuntimeException('Unable to resolve the selected Super Admin.');
        }

        return [
            'name' => (string) $admin->getAttribute('name'),
            'username' => (string) $admin->getAttribute('username'),
            'email' => (string) $admin->getAttribute('email'),
            'password_hash' => $admin->getAuthPassword(),
        ];
    }

    /** @param list<string> $arguments */
    private function gitValue(array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->laravel->basePath());
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : '';
    }

    private function displayValue(string $key, string $value): string
    {
        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($key, $part) && $value !== '') {
                return '[redacted]';
            }
        }

        return $value === '' ? '[blank]' : $value;
    }

    private static function truthy(string|bool $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function boolean(string|bool $value): string
    {
        return self::truthy($value) ? 'true' : 'false';
    }

    private static function featureEnabled(string $feature, string $value): bool
    {
        return match ($feature) {
            'reverb' => $value === 'reverb',
            'scout' => ! in_array($value, ['', 'collection'], true),
            default => self::truthy($value),
        };
    }
}
