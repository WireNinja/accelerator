<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Configuration\EnvironmentStore;
use WireNinja\Accelerator\Configuration\SshConfig;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class ConfigureCommand extends Command
{
    protected $signature = 'accelerator:configure
        {scope? : application, features, deployment, or environment}
        {--stage= : staging or production}';

    protected $description = 'Configure Accelerator through validated local files';

    /** @var array<string, string> */
    private const FEATURE_KEYS = [
        'oauth' => 'ACCELERATOR_FEATURE_OAUTH',
        'pwa' => 'ACCELERATOR_FEATURE_PWA',
        'telegram' => 'ACCELERATOR_FEATURE_TELEGRAM',
        'horizon' => 'ACCELERATOR_FEATURE_HORIZON',
        'reverb' => 'BROADCAST_CONNECTION',
        'scout' => 'SCOUT_DRIVER',
        'nightwatch' => 'NIGHTWATCH_ENABLED',
    ];

    public function handle(): int
    {
        $scope = (string) ($this->argument('scope') ?: select('Configuration section', [
            'application' => 'Application identity and local URL',
            'features' => 'Optional runtime integrations',
            'deployment' => 'Mutable single-VPS deployment topology',
            'environment' => 'Secret Laravel stage environment',
        ]));

        if (! in_array($scope, ['application', 'features', 'deployment', 'environment'], true)) {
            $this->components->error("Unknown configuration section [{$scope}].");

            return self::FAILURE;
        }

        try {
            $store = new EnvironmentStore($this->laravel->basePath());

            return match ($scope) {
                'application' => $this->configureApplication($store),
                'features' => $this->configureFeatures($store),
                'deployment' => $this->configureDeployment($store),
                'environment' => $this->configureEnvironment($store),
            };
        } catch (RuntimeException|JsonException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function configureApplication(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $name = text('Application name', default: $current['APP_NAME'] ?? (string) config('app.name'), required: true);
        $url = text(
            'Local application URL',
            default: $current['APP_URL'] ?? 'http://localhost:8000',
            required: true,
            validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL) ? null : 'Enter an absolute URL.',
        );
        $draft = [
            'APP_NAME' => $name,
            'APP_URL' => $url,
            'VITE_APP_NAME' => $name,
            'GOOGLE_REDIRECT_URI' => rtrim($url, '/').'/auth/google/callback',
        ];

        if (! $this->confirmDraft('.env', $current, $draft)) {
            return self::SUCCESS;
        }

        $store->merge('.env', $draft);
        $store->merge('.env.example', $draft);
        $this->refreshCaches();

        return self::SUCCESS;
    }

    private function configureFeatures(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $selected = multiselect(
            'Active optional integrations',
            options: array_combine(array_keys(self::FEATURE_KEYS), array_keys(self::FEATURE_KEYS)),
            default: array_keys(array_filter(self::FEATURE_KEYS, static fn (string $key, string $feature): bool => match ($feature) {
                'reverb' => ($current[$key] ?? 'log') === 'reverb',
                'scout' => ($current[$key] ?? 'collection') === 'database',
                default => self::truthy($current[$key] ?? 'false'),
            }, ARRAY_FILTER_USE_BOTH)),
            hint: 'Filament, settings, and RBAC are always active.',
        );
        $draft = [];

        foreach (self::FEATURE_KEYS as $feature => $key) {
            $enabled = in_array($feature, $selected, true);
            $draft[$key] = match ($feature) {
                'reverb' => $enabled ? 'reverb' : 'log',
                'scout' => $enabled ? 'database' : 'collection',
                default => $enabled ? 'true' : 'false',
            };
        }

        $draft['ACCELERATOR_OAUTH_MODE'] = in_array('oauth', $selected, true) ? 'existing_only' : 'disabled';

        if (! $this->confirmDraft('.env', $current, $draft)) {
            return self::SUCCESS;
        }

        $store->merge('.env', $draft);
        $store->merge('.env.example', $draft);
        $this->refreshCaches();

        return self::SUCCESS;
    }

    /** @throws JsonException */
    private function configureDeployment(EnvironmentStore $store): int
    {
        DeploymentConfig::assertNoLegacyFiles($this->laravel->basePath());
        $current = $this->deploymentDocument();
        $local = $store->read('.env');
        $aliases = SshConfig::aliases();
        $production = is_array($current['stages']['production'] ?? null) ? $current['stages']['production'] : [];
        $currentHost = is_string($production['ssh_host'] ?? null) ? $production['ssh_host'] : '';
        $sshHost = $aliases === []
            ? text('SSH host alias from ~/.ssh/config', default: $currentHost, required: true)
            : select('SSH host alias', array_combine($aliases, $aliases), default: in_array($currentHost, $aliases, true) ? $currentHost : null);
        $domain = text('Production domain', default: is_string($production['domain'] ?? null) ? $production['domain'] : '', required: true);
        $stagingEnabled = confirm('Configure staging too?', default: (bool) ($current['stages']['staging']['enabled'] ?? false));
        $stagingDomain = $stagingEnabled
            ? text('Staging domain', default: is_string($current['stages']['staging']['domain'] ?? null) ? $current['stages']['staging']['domain'] : "staging.{$domain}", required: true)
            : '';
        $runtime = select('HTTP runtime', ['octane' => 'Octane + Swoole', 'fpm' => 'PHP-FPM'], default: is_string($production['http_runtime'] ?? null) ? $production['http_runtime'] : 'octane');
        $horizon = self::truthy($local['ACCELERATOR_FEATURE_HORIZON'] ?? 'false');
        $reverb = ($local['BROADCAST_CONNECTION'] ?? 'log') === 'reverb';
        $nightwatch = self::truthy($local['NIGHTWATCH_ENABLED'] ?? 'false');
        $packageManager = $this->packageManager();
        $project = is_string($current['project'] ?? null) ? $current['project'] : basename($this->laravel->basePath());
        $repository = is_string($current['repository'] ?? null) ? $current['repository'] : $this->gitValue(['remote', 'get-url', 'origin']);
        $branch = is_string($current['branch'] ?? null) ? $current['branch'] : ($this->gitValue(['branch', '--show-current']) ?: 'main');

        DeploymentConfig::validateInstallerTargets(
            project: $project,
            sshHost: $sshHost,
            repository: $repository,
            branch: $branch,
            productionDomain: $domain,
            productionRoot: "/var/www/{$domain}",
            stagingDomain: $stagingEnabled ? $stagingDomain : null,
            stagingRoot: $stagingEnabled ? "/var/www/{$stagingDomain}" : null,
        );

        $document = [
            'schema' => 1,
            'default_stage' => $stagingEnabled ? 'staging' : 'production',
            'project' => $project,
            'repository' => $repository,
            'branch' => $branch,
            'keep_releases' => 5,
            'php_version' => '8.5',
            'php_binary' => 'php8.5',
            'package_manager' => $packageManager,
            'run_user' => 'www-data',
            'ssl_email' => $local['MAIL_FROM_ADDRESS'] ?? '',
            'stages' => [
                'staging' => $this->stageTopology($stagingEnabled, $sshHost, $stagingDomain, $runtime, $horizon, $reverb, $nightwatch, 8100, 8180, 2507),
                'production' => $this->stageTopology(true, $sshHost, $domain, $runtime, $horizon, $reverb, $nightwatch, 8000, 8080, 2407),
            ],
        ];

        note('Affected committed file: .accelerator/deploy.json. No SSH connection will be opened.');

        if (! confirm('Write deployment topology?', default: false)) {
            return self::SUCCESS;
        }

        $this->writeDeploymentDocument($document);
        $this->ensureStageEnvironment($store, 'production', $domain, $local);

        if ($stagingEnabled) {
            $this->ensureStageEnvironment($store, 'staging', $stagingDomain, $local);
        }

        $this->components->info('Deployment configuration updated. Review deploy.json, then configure each stage environment.');

        return self::SUCCESS;
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

        $path = ".accelerator/environments/{$stage}.env";
        $current = $store->read($path);
        $domain = (string) $stageConfig['domain'];
        $draft = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => "https://{$domain}",
            'LOG_LEVEL' => 'error',
        ];

        if (($current['APP_KEY'] ?? '') === '') {
            $draft['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        if (($current['DB_CONNECTION'] ?? 'sqlite') === 'sqlite') {
            $draft['DB_DATABASE'] = "/var/www/{$domain}/shared/database/database.sqlite";
        } else {
            $draft['DB_HOST'] = text('Database host', default: $current['DB_HOST'] ?? '127.0.0.1', required: true);
            $draft['DB_DATABASE'] = text('Database name', default: $current['DB_DATABASE'] ?? '', required: true);
            $draft['DB_USERNAME'] = text('Database user', default: $current['DB_USERNAME'] ?? '', required: true);
            $draft['DB_PASSWORD'] = text('Database password', default: $current['DB_PASSWORD'] ?? '', required: true);
        }

        if (! $this->confirmDraft($path, $current, $draft)) {
            return self::SUCCESS;
        }

        $store->merge($path, $draft);
        $this->components->info("Stage saved. Next: php artisan accelerator:deploy:init --stage={$stage}");

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function stageTopology(bool $enabled, string $sshHost, string $domain, string $runtime, bool $horizon, bool $reverb, bool $nightwatch, int $octanePort, int $reverbPort, int $nightwatchPort): array
    {
        return [
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
            'octane_port' => $octanePort,
            'reverb_port' => $reverbPort,
            'nightwatch_port' => $nightwatchPort,
            'health_path' => '/up',
        ];
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

    /** @param array<string, string> $current @param array<string, string> $draft */
    private function confirmDraft(string $path, array $current, array $draft): bool
    {
        $rows = [];

        foreach ($draft as $key => $value) {
            if (($current[$key] ?? null) !== $value) {
                $rows[] = [$key, $current[$key] ?? '[blank]', str_contains($key, 'PASSWORD') ? '[redacted]' : $value];
            }
        }

        if ($rows === []) {
            note("No changes for {$path}.");

            return false;
        }

        $this->table(['Key', 'Current', 'Draft'], $rows);

        return confirm("Write {$path}?", default: false);
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
