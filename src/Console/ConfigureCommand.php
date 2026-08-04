<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Configuration\EnvironmentStore;
use WireNinja\Accelerator\Configuration\SshConfig;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\LegacyDeploymentConfig;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class ConfigureCommand extends Command
{
    protected $signature = 'accelerator:configure
        {scope? : application, features, deployment, or environment}
        {--stage= : staging or production}
        {--app-name= : Application name}
        {--app-url= : Absolute local application URL}
        {--features= : Comma-separated enabled optional features}
        {--ssh-host= : SSH alias for deployment}
        {--domain= : Production domain}
        {--staging-domain= : Staging domain; empty disables staging}
        {--deployment-key= : Stable lowercase deployment identity}
        {--port-base= : First port in the reserved deployment block}
        {--ssl-email= : Email used for ACME certificate registration}
        {--http-runtime= : octane or fpm}
        {--rotate-app-key : Generate a new APP_KEY for the selected stage}
        {--rotate-reverb-credentials : Generate new Reverb credentials for the selected stage}
        {--migrate-legacy : Convert .accelerator/deploy.env to deploy.json and delete the legacy file}
        {--force : Confirm a validated non-interactive write}
        {--json : Emit a stable JSON result}';

    protected $description = 'Configure Accelerator through validated local files';

    /** @var array<string, string> */
    private const FEATURE_KEYS = [
        'oauth' => 'ACCELERATOR_FEATURE_OAUTH',
        'pwa' => 'ACCELERATOR_FEATURE_PWA',
        'telegram' => 'ACCELERATOR_FEATURE_TELEGRAM',
        'horizon' => 'ACCELERATOR_FEATURE_HORIZON',
        'reverb' => 'ACCELERATOR_FEATURE_REVERB',
        'scout' => 'ACCELERATOR_FEATURE_SCOUT',
        'nightwatch' => 'ACCELERATOR_FEATURE_NIGHTWATCH',
    ];

    public function handle(): int
    {
        $scope = (string) $this->argument('scope');

        if ($scope === '') {
            if (! $this->interactive()) {
                return $this->failure('A configuration scope is required in non-interactive mode.');
            }

            $scope = select('Configuration section', [
                'application' => 'Application identity and local URL',
                'features' => 'Optional runtime integrations',
                'deployment' => 'Mutable single-VPS deployment topology',
                'environment' => 'Secret Laravel stage environment',
            ]);
        }

        if (! in_array($scope, ['application', 'features', 'deployment', 'environment'], true)) {
            return $this->failure("Unknown configuration section [{$scope}].");
        }

        try {
            $store = new EnvironmentStore($this->laravel->basePath());

            return match ($scope) {
                'application' => $this->configureApplication($store),
                'features' => $this->configureFeatures($store),
                'deployment' => $this->configureDeployment($store),
                'environment' => $this->configureEnvironment($store),
            };
        } catch (InvalidArgumentException|RuntimeException|JsonException $exception) {
            return $this->failure($exception->getMessage());
        }
    }

    private function configureApplication(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $name = $this->stringOption('app-name') ?? ($this->interactive()
            ? text('Application name', default: $current['APP_NAME'] ?? (string) config('app.name'), required: true)
            : ($current['APP_NAME'] ?? (string) config('app.name')));
        $url = $this->stringOption('app-url') ?? ($this->interactive()
            ? text(
                'Local application URL',
                default: $current['APP_URL'] ?? 'http://localhost:8000',
                required: true,
                validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL) ? null : 'Enter an absolute URL.',
            )
            : ($current['APP_URL'] ?? 'http://localhost:8000'));

        if (trim($name) === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Application name and an absolute --app-url are required.');
        }
        $draft = [
            'APP_NAME' => $name,
            'APP_URL' => $url,
            'VITE_APP_NAME' => $name,
            'GOOGLE_REDIRECT_URI' => rtrim($url, '/').'/auth/google/callback',
        ];

        if (! $this->confirmDraft('.env', $current, $draft)) {
            return $this->success('application', false, []);
        }

        $store->merge('.env', $draft);
        $store->merge('.env.example', $draft);
        $this->refreshCaches();

        return $this->success('application', true, ['.env', '.env.example']);
    }

    private function configureFeatures(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $featureOption = $this->option('features');

        if (is_string($featureOption)) {
            $selected = array_values(array_filter(explode(',', $featureOption), static fn (string $feature): bool => $feature !== ''));
        } elseif ($this->interactive()) {
            $selected = multiselect(
                'Active optional integrations',
                options: array_combine(array_keys(self::FEATURE_KEYS), array_keys(self::FEATURE_KEYS)),
                default: array_keys(array_filter(self::FEATURE_KEYS, static fn (string $key): bool => self::truthy($current[$key] ?? 'false'))),
                hint: 'Filament, settings, and RBAC are always active.',
            );
        } else {
            throw new RuntimeException('Non-interactive feature configuration requires --features=oauth,pwa,...; pass --features= to disable all.');
        }

        $unknown = array_values(array_diff($selected, array_keys(self::FEATURE_KEYS)));

        if ($unknown !== []) {
            throw new RuntimeException('Unknown Accelerator features: '.implode(', ', $unknown));
        }
        $draft = [];

        foreach (self::FEATURE_KEYS as $feature => $key) {
            $enabled = in_array($feature, $selected, true);
            $draft[$key] = $enabled ? 'true' : 'false';
        }

        $currentOAuthMode = $current['ACCELERATOR_OAUTH_MODE'] ?? 'disabled';
        $draft['ACCELERATOR_OAUTH_MODE'] = in_array('oauth', $selected, true)
            ? (in_array($currentOAuthMode, ['existing_only', 'allowed_domains'], true) ? $currentOAuthMode : 'existing_only')
            : 'disabled';
        $draft['BROADCAST_CONNECTION'] = in_array('reverb', $selected, true) ? 'reverb' : 'log';
        $draft['SCOUT_DRIVER'] = in_array('scout', $selected, true) ? 'database' : 'collection';
        $draft['NIGHTWATCH_ENABLED'] = in_array('nightwatch', $selected, true) ? 'true' : 'false';

        if (! $this->confirmDraft('.env', $current, $draft)) {
            return $this->success('features', false, []);
        }

        $store->merge('.env', $draft);
        $store->merge('.env.example', $draft);
        $this->refreshCaches();

        return $this->success('features', true, ['.env', '.env.example']);
    }

    /** @throws JsonException */
    private function configureDeployment(EnvironmentStore $store): int
    {
        if ($this->option('migrate-legacy')) {
            return $this->migrateLegacyDeployment();
        }

        DeploymentConfig::assertNoLegacyFiles($this->laravel->basePath());
        $current = $this->deploymentDocument();
        $local = $store->read('.env');
        $aliases = SshConfig::aliases();
        $production = is_array($current['stages']['production'] ?? null) ? $current['stages']['production'] : [];
        $staging = is_array($current['stages']['staging'] ?? null) ? $current['stages']['staging'] : [];
        $currentHost = is_string($production['ssh_host'] ?? null) ? $production['ssh_host'] : '';
        $sshHost = $this->stringOption('ssh-host') ?? ($this->interactive()
            ? ($aliases === []
                ? text('SSH host alias from ~/.ssh/config', default: $currentHost, required: true)
                : select('SSH host alias', array_combine($aliases, $aliases), default: in_array($currentHost, $aliases, true) ? $currentHost : null))
            : $currentHost);
        $currentDomain = is_string($production['domain'] ?? null) ? $production['domain'] : '';
        $domain = $this->stringOption('domain') ?? ($this->interactive()
            ? text('Production domain', default: $currentDomain, required: true)
            : $currentDomain);
        $stagingOption = $this->option('staging-domain');
        $stagingEnabled = is_string($stagingOption)
            ? $stagingOption !== ''
            : ($this->interactive()
                ? confirm('Configure staging too?', default: (bool) ($current['stages']['staging']['enabled'] ?? false))
                : (bool) ($current['stages']['staging']['enabled'] ?? false));
        $stagingDomain = $stagingEnabled
            ? (is_string($stagingOption) ? $stagingOption : ($this->interactive()
                ? text('Staging domain', default: is_string($current['stages']['staging']['domain'] ?? null) ? $current['stages']['staging']['domain'] : "staging.{$domain}", required: true)
                : (string) ($current['stages']['staging']['domain'] ?? "staging.{$domain}")))
            : '';
        $runtime = $this->stringOption('http-runtime') ?? ($this->interactive()
            ? select('HTTP runtime', ['octane' => 'Octane + Swoole', 'fpm' => 'PHP-FPM'], default: is_string($production['http_runtime'] ?? null) ? $production['http_runtime'] : 'octane')
            : (is_string($production['http_runtime'] ?? null) ? $production['http_runtime'] : 'octane'));
        $horizon = self::truthy($local['ACCELERATOR_FEATURE_HORIZON'] ?? 'false');
        $reverb = ($local['BROADCAST_CONNECTION'] ?? 'log') === 'reverb';
        $nightwatch = self::truthy($local['NIGHTWATCH_ENABLED'] ?? 'false');
        $packageManager = $this->packageManager();
        $legacyProject = is_string($current['project'] ?? null) ? $current['project'] : '';
        $currentDeploymentKey = is_string($current['deployment_key'] ?? null) ? $current['deployment_key'] : $legacyProject;
        $deploymentKey = $this->stringOption('deployment-key') ?? ($this->interactive()
            ? text('Stable deployment key', default: $currentDeploymentKey !== '' ? $currentDeploymentKey : basename($this->laravel->basePath()), required: true)
            : ($currentDeploymentKey !== '' ? $currentDeploymentKey : basename($this->laravel->basePath())));
        $currentPortBase = is_int($current['port_base'] ?? null) ? $current['port_base'] : 9010;
        $portBaseOption = $this->stringOption('port-base');
        $portBase = $portBaseOption !== null ? filter_var($portBaseOption, FILTER_VALIDATE_INT) : $currentPortBase;

        if (! is_int($portBase)) {
            throw new RuntimeException('--port-base must be an integer.');
        }
        $repository = is_string($current['repository'] ?? null) ? $current['repository'] : $this->gitValue(['remote', 'get-url', 'origin']);
        $branch = is_string($current['branch'] ?? null) ? $current['branch'] : ($this->gitValue(['branch', '--show-current']) ?: 'main');
        $sslEmail = $this->stringOption('ssl-email')
            ?? (is_string($current['ssl_email'] ?? null) && $current['ssl_email'] !== ''
                ? $current['ssl_email']
                : ($local['MAIL_FROM_ADDRESS'] ?? ''));

        DeploymentConfig::validateInstallerTargets(
            deploymentKey: $deploymentKey,
            sshHost: $sshHost,
            repository: $repository,
            branch: $branch,
            productionDomain: $domain,
            productionRoot: "/var/www/{$domain}",
            stagingDomain: $stagingEnabled ? $stagingDomain : null,
            stagingRoot: $stagingEnabled ? "/var/www/{$stagingDomain}" : null,
        );

        $document = [
            'schema' => 2,
            'default_stage' => $stagingEnabled ? 'staging' : 'production',
            'deployment_key' => $deploymentKey,
            'repository' => $repository,
            'branch' => $branch,
            'port_base' => $portBase,
            'keep_releases' => is_int($current['keep_releases'] ?? null) ? $current['keep_releases'] : 5,
            'php_version' => is_string($current['php_version'] ?? null) ? $current['php_version'] : '8.5',
            'php_binary' => is_string($current['php_binary'] ?? null) ? $current['php_binary'] : 'php8.5',
            'package_manager' => $packageManager,
            'run_user' => is_string($current['run_user'] ?? null) ? $current['run_user'] : 'www-data',
            'ssl_email' => $sslEmail,
            'stages' => [
                'staging' => $this->stageTopology($staging, $stagingEnabled, $sshHost, $stagingDomain, $runtime, $horizon, $reverb, $nightwatch),
                'production' => $this->stageTopology($production, true, $sshHost, $domain, $runtime, $horizon, $reverb, $nightwatch),
            ],
        ];
        DeploymentConfig::validateTopologyDocument($document);

        if (! $this->option('json')) {
            note('Affected committed file: .accelerator/deploy.json. No SSH connection will be opened.');
        }

        if (! $this->confirmMutation('Write deployment topology?')) {
            return $this->success('deployment', false, []);
        }

        $this->writeDeploymentDocument($document);
        $this->syncLocalEnvironmentIndicator($store, $stagingEnabled);
        $this->ensureStageEnvironment($store, 'production', $domain, $local);
        $this->syncEnvironmentIndicator($store, 'production', $stagingEnabled);

        if ($stagingEnabled) {
            $this->ensureStageEnvironment($store, 'staging', $stagingDomain, $local);
            $this->syncEnvironmentIndicator($store, 'staging', true);
        } elseif ($store->read('.accelerator/environments/staging.env') !== []) {
            $this->syncEnvironmentIndicator($store, 'staging', false);
        }

        $oldRoot = is_string($production['root'] ?? null) ? $production['root'] : '';
        $newRoot = "/var/www/{$domain}";
        $next = $oldRoot !== '' && $oldRoot !== $newRoot
            ? "Run php artisan accelerator:deploy:relocate {$oldRoot} --stage=production after reviewing the new topology."
            : 'Review deploy.json, then configure each stage environment.';

        if (! $this->option('json')) {
            $this->components->info('Deployment configuration updated. '.$next);
        }

        return $this->success('deployment', true, ['.accelerator/deploy.json'], $next);
    }

    /** @throws JsonException */
    private function migrateLegacyDeployment(): int
    {
        $root = $this->laravel->basePath();
        $legacyValues = (new EnvironmentStore($root))->read('.accelerator/deploy.env');
        $oldProductionRoot = $legacyValues['OPS_DEPLOY_PRODUCTION_ROOT'] ?? '';
        $document = LegacyDeploymentConfig::read($root, $this->packageManager());

        if (! $this->confirmMutation('Write deploy.json and remove the legacy deploy.env?')) {
            return $this->success('deployment', false, []);
        }

        $this->writeDeploymentDocument($document);
        $legacy = $root.'/.accelerator/deploy.env';

        if (is_file($legacy) && ! unlink($legacy)) {
            throw new RuntimeException('deploy.json was written, but the legacy deploy.env could not be removed.');
        }

        $production = is_array($document['stages']['production'] ?? null) ? $document['stages']['production'] : [];
        $newProductionRoot = is_string($production['root'] ?? null) ? $production['root'] : '';
        $next = $oldProductionRoot !== '' && $oldProductionRoot !== $newProductionRoot
            ? "Review deploy.json, then run php artisan accelerator:deploy:relocate {$oldProductionRoot} --stage=production."
            : 'Run php artisan accelerator:doctor --section=deployment, then review before any remote mutation.';

        return $this->success(
            'deployment',
            true,
            ['.accelerator/deploy.json', '.accelerator/deploy.env (removed)'],
            $next,
        );
    }

    private function configureEnvironment(EnvironmentStore $store): int
    {
        $document = $this->deploymentDocument();
        $stage = (string) ($this->option('stage') ?: select('Environment stage', [
            'staging' => 'staging',
            'production' => 'production',
        ]));
        $stageConfig = $document['stages'][$stage] ?? null;

        if (! is_array($stageConfig) || ($stageConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException("Deployment stage [{$stage}] is disabled.");
        }

        $deployment = DeploymentConfig::load($this->laravel->basePath(), $stage, validateRuntime: false);

        $path = ".accelerator/environments/{$stage}.env";
        $current = $store->read($path);
        $domain = (string) $stageConfig['domain'];
        $draft = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => "https://{$domain}",
            'LOG_LEVEL' => 'error',
            'OCTANE_PORT' => (string) $deployment->octanePort,
            'REVERB_SERVER_PORT' => (string) $deployment->reverbPort,
            'NIGHTWATCH_INGEST_URI' => "127.0.0.1:{$deployment->nightwatchPort}",
        ];
        $dualStage = collect($document['stages'] ?? [])->filter(
            static fn (mixed $configuredStage): bool => is_array($configuredStage)
                && ($configuredStage['enabled'] ?? false) === true,
        )->count() > 1;
        $draft += $this->environmentIndicatorValues($current, $stage, $dualStage);
        $draft += $this->stageRuntimeIdentityValues((string) $document['deployment_key'], $stage);
        $local = $store->read('.env');

        foreach (self::FEATURE_KEYS as $feature => $key) {
            $enabled = match ($feature) {
                'horizon', 'reverb', 'nightwatch' => ($stageConfig[$feature] ?? false) === true,
                default => self::truthy($local[$key] ?? 'false'),
            };
            $draft[$key] = $enabled ? 'true' : 'false';
        }

        if ($this->option('rotate-app-key') || ($current['APP_KEY'] ?? '') === '') {
            $draft['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        if (($stageConfig['reverb'] ?? false) === true) {
            $draft += [
                'REVERB_HOST' => $domain,
                'REVERB_PORT' => '443',
                'REVERB_SCHEME' => 'https',
                'VITE_REVERB_HOST' => $domain,
                'VITE_REVERB_PORT' => '443',
                'VITE_REVERB_SCHEME' => 'https',
            ];

            if ($this->option('rotate-reverb-credentials')
                || ($current['REVERB_APP_ID'] ?? '') === ''
                || ($current['REVERB_APP_KEY'] ?? '') === ''
                || ($current['REVERB_APP_SECRET'] ?? '') === '') {
                $reverbKey = bin2hex(random_bytes(16));
                $draft += [
                    'REVERB_APP_ID' => bin2hex(random_bytes(8)),
                    'REVERB_APP_KEY' => $reverbKey,
                    'REVERB_APP_SECRET' => bin2hex(random_bytes(32)),
                    'VITE_REVERB_APP_KEY' => $reverbKey,
                ];
            } else {
                $draft['VITE_REVERB_APP_KEY'] = $current['REVERB_APP_KEY'];
            }
        }

        if (($current['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
            $draft['DB_DATABASE'] = "/var/www/{$domain}/shared/database/database.sqlite";
        } else {
            if ($this->interactive()) {
                $draft['DB_HOST'] = text('Database host', default: $current['DB_HOST'] ?? '127.0.0.1', required: true);
                $draft['DB_DATABASE'] = text('Database name', default: $current['DB_DATABASE'] ?? '', required: true);
                $draft['DB_USERNAME'] = text('Database user', default: $current['DB_USERNAME'] ?? '', required: true);
                $draft['DB_PASSWORD'] = text('Database password', default: $current['DB_PASSWORD'] ?? '', required: true);
            } else {
                foreach (['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
                    if (($current[$key] ?? '') === '') {
                        throw new RuntimeException("{$key} must already exist in {$path} before non-interactive configuration.");
                    }

                    $draft[$key] = $current[$key];
                }

                if (($current['DB_SOCKET'] ?? '') !== '') {
                    $draft['DB_SOCKET'] = $current['DB_SOCKET'];
                } elseif (($current['DB_HOST'] ?? '') !== '') {
                    $draft['DB_HOST'] = $current['DB_HOST'];
                } else {
                    throw new RuntimeException("DB_SOCKET or DB_HOST must already exist in {$path} before non-interactive configuration.");
                }
            }
        }

        if (! $this->confirmDraft($path, $current, $draft)) {
            return $this->success('environment', false, []);
        }

        $store->merge($path, $draft);
        $next = "php artisan accelerator:deploy:init --stage={$stage}";

        if (! $this->option('json')) {
            $this->components->info("Stage saved. Next: {$next}");
        }

        return $this->success('environment', true, [$path], $next);
    }

    /** @param array<string, mixed> $current @return array<string, mixed> */
    private function stageTopology(array $current, bool $enabled, string $sshHost, string $domain, string $runtime, bool $horizon, bool $reverb, bool $nightwatch): array
    {
        unset($current['service_group'], $current['octane_port'], $current['reverb_port'], $current['nightwatch_port']);

        return array_replace($current + [
            'enabled' => $enabled,
            'ssh_host' => $sshHost,
            'domain' => $domain,
            'root' => $domain === '' ? '' : "/var/www/{$domain}",
            'http_runtime' => $runtime,
            'horizon' => $horizon,
            'queue_worker' => ! $horizon,
            'reverb' => $reverb,
            'nightwatch' => $nightwatch,
            'scheduler' => true,
            'health_path' => '/up',
        ], [
            'enabled' => $enabled,
            'ssh_host' => $sshHost,
            'domain' => $domain,
            'root' => $domain === '' ? '' : "/var/www/{$domain}",
            'http_runtime' => $runtime,
            'horizon' => $horizon,
            'queue_worker' => ! $horizon,
            'reverb' => $reverb,
            'nightwatch' => $nightwatch,
        ]);
    }

    /** @return array<string, mixed> */
    private function deploymentDocument(): array
    {
        $contents = @file_get_contents($this->laravel->basePath('.accelerator/deploy.json'));

        if (! is_string($contents)) {
            return [];
        }

        $document = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return is_array($document) ? $document : [];
    }

    /** @param array<string, mixed> $document @throws JsonException */
    private function writeDeploymentDocument(array $document): void
    {
        $directory = $this->laravel->basePath('.accelerator');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create .accelerator directory.');
        }

        $payload = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        if (file_put_contents($directory.'/deploy.json', $payload, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write .accelerator/deploy.json.');
        }

        $this->updateDeploymentGitignore();
    }

    private function updateDeploymentGitignore(): void
    {
        $path = $this->laravel->basePath('.gitignore');
        $contents = is_file($path) ? file_get_contents($path) : '';

        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read .gitignore.');
        }

        $contents = preg_replace('/^\/\.accelerator\/[ \t]*$\R?/m', '', $contents) ?? $contents;

        foreach (['/.accelerator/install-state.json', '/.accelerator/environments/'] as $entry) {
            if (! preg_match('/^'.preg_quote($entry, '/').'$/m', $contents)) {
                $contents = rtrim($contents).PHP_EOL.$entry.PHP_EOL;
            }
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to update .gitignore deployment ownership.');
        }
    }

    /** @param array<string, string> $local */
    private function ensureStageEnvironment(EnvironmentStore $store, string $stage, string $domain, array $local): void
    {
        $path = ".accelerator/environments/{$stage}.env";

        if ($store->read($path) !== []) {
            return;
        }

        $environment = $local;
        $environment['APP_ENV'] = 'production';
        $environment['APP_DEBUG'] = 'false';
        $environment['APP_URL'] = "https://{$domain}";
        $environment['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));

        foreach (['DB_PASSWORD', 'GOOGLE_CLIENT_SECRET', 'NIGHTWATCH_TOKEN', 'TELEGRAM_BOT_TOKEN', 'VAPID_PRIVATE_KEY'] as $key) {
            $environment[$key] = '';
        }

        if (($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
            $environment['DB_DATABASE'] = "/var/www/{$domain}/shared/database/database.sqlite";
        }

        $store->replace($path, $environment);
    }

    private function syncEnvironmentIndicator(EnvironmentStore $store, string $stage, bool $dualStage): void
    {
        $path = ".accelerator/environments/{$stage}.env";
        $current = $store->read($path);

        $store->merge($path, $this->environmentIndicatorValues($current, $stage, $dualStage));
    }

    private function syncLocalEnvironmentIndicator(EnvironmentStore $store, bool $dualStage): void
    {
        $current = $store->read('.env');
        $label = trim($current['ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL'] ?? '');
        $color = trim($current['ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR'] ?? '');

        $store->merge('.env', [
            'ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED' => $dualStage ? 'true' : 'false',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL' => $label !== '' ? $label : 'LOCAL DATA',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR' => $label !== '' && $color !== '' ? $color : 'info',
        ]);
    }

    /** @param array<string, string> $current @return array<string, string> */
    private function environmentIndicatorValues(array $current, string $stage, bool $dualStage): array
    {
        $label = trim($current['ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL'] ?? '');
        $color = trim($current['ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR'] ?? '');

        return [
            'ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED' => $dualStage ? 'true' : 'false',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL' => $label !== '' ? $label : ($stage === 'staging' ? 'TEST DATA' : 'LIVE DATA'),
            'ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR' => $label !== '' && $color !== '' ? $color : ($stage === 'staging' ? 'warning' : 'danger'),
        ];
    }

    /** @return array<string, string> */
    private function stageRuntimeIdentityValues(string $project, string $stage): array
    {
        $prefix = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', "{$project}_{$stage}"));

        return [
            'REDIS_PREFIX' => "{$prefix}_database_",
            'CACHE_PREFIX' => "{$prefix}_cache_",
            'HORIZON_NAME' => "{$project}-{$stage}",
            'HORIZON_PREFIX' => "{$prefix}_horizon:",
            'SESSION_COOKIE' => "{$prefix}_session",
        ];
    }

    /** @param array<string, string> $current @param array<string, string> $draft */
    private function confirmDraft(string $path, array $current, array $draft): bool
    {
        $rows = [];

        foreach ($draft as $key => $value) {
            if (($current[$key] ?? null) !== $value) {
                $sensitive = preg_match('/(?:KEY|PASSWORD|SECRET|TOKEN)$/', $key) === 1;
                $rows[] = [
                    $key,
                    $sensitive && isset($current[$key]) ? '[redacted]' : ($current[$key] ?? '[blank]'),
                    $sensitive ? '[redacted]' : $value,
                ];
            }
        }

        if ($rows === []) {
            if (! $this->option('json')) {
                note("No changes for {$path}.");
            }

            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        if (! $this->interactive()) {
            throw new RuntimeException('Validated changes are pending. Rerun with --force to write them non-interactively.');
        }

        $this->table(['Key', 'Current', 'Draft'], $rows);

        return confirm("Write {$path}?", default: false);
    }

    private function confirmMutation(string $question): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->interactive()) {
            throw new RuntimeException('Rerun with --force to confirm the validated non-interactive mutation.');
        }

        return confirm($question, default: false);
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    private function interactive(): bool
    {
        return $this->input->isInteractive() && ! $this->option('json');
    }

    /** @param list<string> $files @throws JsonException */
    private function success(string $scope, bool $changed, array $files, ?string $next = null): int
    {
        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'schema' => 1,
                'status' => 'OK',
                'scope' => $scope,
                'changed' => $changed,
                'files' => $files,
                'next' => $next,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }

    private function failure(string $message): int
    {
        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'schema' => 1,
                'status' => 'ERROR',
                'error' => $message,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }

    private function packageManager(): string
    {
        $contents = @file_get_contents($this->laravel->basePath('package.json'));
        $package = is_string($contents) ? json_decode($contents, true) : null;
        $specification = is_array($package) && is_string($package['packageManager'] ?? null) ? $package['packageManager'] : '';

        return str_starts_with($specification, 'npm@') ? 'npm' : 'pnpm';
    }

    /** @param list<string> $arguments */
    private function gitValue(array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->laravel->basePath());
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : '';
    }

    private function refreshCaches(): void
    {
        $this->callSilent('config:clear');
        $this->callSilent('route:clear');
    }

    private static function truthy(string|bool $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
