<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Configuration\EnvironmentStore;
use WireNinja\Accelerator\Configuration\ReverbApplicationRegistry;
use WireNinja\Accelerator\Configuration\SshConfig;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
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
        {--ssl-email= : Email used for ACME certificate registration}
        {--rotate-app-key : Generate a new APP_KEY for the selected stage}
        {--rotate-reverb-app : Generate new centralized Reverb credentials for the selected stage}
        {--force : Confirm a validated non-interactive write}
        {--json : Emit a stable JSON result}';

    protected $description = 'Configure Accelerator through validated local files';

    /** @var array<string, string> */
    private const FEATURE_KEYS = [
        'oauth' => 'ACCELERATOR_FEATURE_OAUTH',
        'pwa' => 'ACCELERATOR_FEATURE_PWA',
        'telegram' => 'ACCELERATOR_FEATURE_TELEGRAM',
        'realtime' => 'ACCELERATOR_FEATURE_REALTIME',
        'scout' => 'ACCELERATOR_FEATURE_SCOUT',
        'observability' => 'ACCELERATOR_FEATURE_OBSERVABILITY',
    ];

    public function handle(): int
    {
        try {
            $scope = (string) $this->argument('scope');

            if ($scope === '') {
                if (! $this->interactive()) {
                    throw new RuntimeException('A configuration scope is required in non-interactive mode.');
                }

                $scope = select('Configuration section', [
                    'application' => 'Application identity and local URL',
                    'features' => 'Optional integrations',
                    'deployment' => 'Single-VPS deployment topology',
                    'environment' => 'Ignored stage runtime environment',
                ]);
            }

            $store = new EnvironmentStore(base_path());

            return match ($scope) {
                'application' => $this->configureApplication($store),
                'features' => $this->configureFeatures($store),
                'deployment' => $this->configureDeployment(),
                'environment' => $this->configureEnvironment($store),
                default => throw new RuntimeException("Unknown configuration section [{$scope}]."),
            };
        } catch (RuntimeException|JsonException $exception) {
            return $this->failure($exception->getMessage());
        }
    }

    private function configureApplication(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $name = $this->stringOption('app-name') ?? ($this->interactive() ? text('Application name', default: $current['APP_NAME'] ?? 'Laravel', required: true) : ($current['APP_NAME'] ?? 'Laravel'));
        $url = $this->stringOption('app-url') ?? ($this->interactive() ? text('Local application URL', default: $current['APP_URL'] ?? 'http://localhost:8000', required: true) : ($current['APP_URL'] ?? 'http://localhost:8000'));

        if ($name === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Application name and an absolute application URL are required.');
        }

        $draft = ['APP_NAME' => $name, 'APP_URL' => $url, 'VITE_APP_NAME' => $name, 'GOOGLE_REDIRECT_URI' => rtrim($url, '/').'/auth/google/callback'];
        $store->merge('.env', $draft);
        $store->merge('.env.example', $draft);

        return $this->success('application', ['.env', '.env.example']);
    }

    private function configureFeatures(EnvironmentStore $store): int
    {
        $current = $store->read('.env');
        $option = $this->option('features');

        if (is_string($option)) {
            $selected = array_values(array_filter(explode(',', $option)));
        } elseif ($this->interactive()) {
            $selected = multiselect('Active optional integrations', array_combine(array_keys(self::FEATURE_KEYS), array_keys(self::FEATURE_KEYS)), default: array_keys(array_filter(self::FEATURE_KEYS, static fn (string $key): bool => ($current[$key] ?? 'false') === 'true')));
        } else {
            throw new RuntimeException('Non-interactive feature configuration requires --features=.');
        }

        $unknown = array_diff($selected, array_keys(self::FEATURE_KEYS));

        if ($unknown !== []) {
            throw new RuntimeException('Unknown Accelerator features: '.implode(', ', $unknown));
        }

        $draft = [];

        foreach (self::FEATURE_KEYS as $feature => $key) {
            $draft[$key] = in_array($feature, $selected, true) ? 'true' : 'false';
        }

        $draft += [
            'BROADCAST_CONNECTION' => in_array('realtime', $selected, true) ? 'reverb' : 'log',
            'SCOUT_DRIVER' => in_array('scout', $selected, true) ? 'database' : 'collection',
            'LOG_STACK' => in_array('observability', $selected, true) ? 'daily,otlp' : 'daily',
            'OTEL_SDK_DISABLED' => in_array('observability', $selected, true) ? 'false' : 'true',
            'OTEL_INSTRUMENTATION_HTTP_SERVER' => 'false',
            'ACCELERATOR_OAUTH_MODE' => in_array('oauth', $selected, true) ? ($current['ACCELERATOR_OAUTH_MODE'] ?? 'existing_only') : 'disabled',
        ];

        $deploymentKey = $this->localDeploymentKey();
        $serviceName = "{$deploymentKey}-local";
        $draft += [
            'OTEL_SERVICE_NAME' => $serviceName,
            'OTEL_SERVICE_INSTANCE_ID' => $serviceName,
            'OTEL_RESOURCE_ATTRIBUTES' => "service.namespace=accelerator,deployment.environment.name=local,service.instance.id={$serviceName}",
        ];

        $exampleDraft = $draft;
        $files = ['.env', '.env.example'];

        if (in_array('realtime', $selected, true)) {
            $application = $this->existingOrGeneratedReverbApplication(
                name: "{$deploymentKey}-local",
                origin: $current['APP_URL'] ?? 'http://localhost:8000',
                environment: $current,
                rotate: (bool) $this->option('rotate-reverb-app'),
            );
            (new ReverbApplicationRegistry(base_path()))->upsert($application);
            $draft = [...$draft, ...$this->reverbEnvironment($application)];
            $files[] = ReverbApplicationRegistry::PATH;
        }

        $store->merge('.env', $draft);
        $store->merge('.env.example', $exampleDraft);

        return $this->success('features', $files);
    }

    /** @throws JsonException */
    private function configureDeployment(): int
    {
        $current = $this->deploymentDocument();
        $production = is_array($current['stages']['production'] ?? null) ? $current['stages']['production'] : [];
        $staging = is_array($current['stages']['staging'] ?? null) ? $current['stages']['staging'] : [];
        $aliases = SshConfig::aliases();
        $host = $this->stringOption('ssh-host') ?? ($this->interactive() ? ($aliases === [] ? text('SSH host alias', required: true) : select('SSH host alias', array_combine($aliases, $aliases))) : (string) ($production['ssh_host'] ?? ''));
        $domain = $this->stringOption('domain') ?? ($this->interactive() ? text('Production domain', default: (string) ($production['domain'] ?? ''), required: true) : (string) ($production['domain'] ?? ''));
        $stagingOption = $this->option('staging-domain');
        $stagingEnabled = is_string($stagingOption) ? $stagingOption !== '' : ($this->interactive() ? confirm('Configure staging too?', default: (bool) ($staging['enabled'] ?? false)) : (bool) ($staging['enabled'] ?? false));
        $stagingDomain = $stagingEnabled ? (is_string($stagingOption) ? $stagingOption : ($this->interactive() ? text('Staging domain', default: (string) ($staging['domain'] ?? "staging.{$domain}"), required: true) : (string) ($staging['domain'] ?? "staging.{$domain}"))) : '';
        $deploymentKey = $this->stringOption('deployment-key') ?? (string) ($current['deployment_key'] ?? basename(base_path()));
        $sslEmail = $this->stringOption('ssl-email') ?? (string) ($current['ssl_email'] ?? config('mail.from.address'));
        $repository = trim((string) shell_exec('git remote get-url origin 2>/dev/null')) ?: (string) ($current['repository'] ?? '');
        $branch = trim((string) shell_exec('git branch --show-current 2>/dev/null')) ?: (string) ($current['branch'] ?? 'main');
        $stage = static fn (bool $enabled, string $stageDomain): array => ['enabled' => $enabled, 'ssh_host' => $host, 'domain' => $stageDomain, 'root' => $stageDomain === '' ? '' : "/var/www/{$stageDomain}", 'health_path' => '/up'];
        $document = [
            'schema' => 3, 'default_stage' => $stagingEnabled ? 'staging' : 'production', 'deployment_key' => $deploymentKey,
            'repository' => $repository, 'branch' => $branch, 'keep_releases' => 5, 'php_version' => '8.5', 'php_binary' => 'php8.5',
            'package_manager' => is_string($current['package_manager'] ?? null) ? $current['package_manager'] : 'pnpm', 'run_user' => 'www-data', 'ssl_email' => $sslEmail,
            'stages' => ['staging' => $stage($stagingEnabled, $stagingDomain), 'production' => $stage(true, $domain)],
        ];
        DeploymentConfig::validateTopologyDocument($document);
        $this->writeJsonFile('.accelerator/deploy.json', $document);

        return $this->success('deployment', ['.accelerator/deploy.json']);
    }

    private function configureEnvironment(EnvironmentStore $store): int
    {
        $stage = (string) ($this->option('stage') ?: 'production');
        $config = DeploymentConfig::load(base_path(), $stage, validateRuntime: false);
        $path = ".accelerator/environments/{$stage}.env";
        $current = $store->read($path);
        $template = $current !== [] ? $current : $store->read('.env.example');
        $prefix = str_replace('-', '_', "{$config->deploymentKey}_{$stage}");
        $serviceName = "{$config->deploymentKey}-{$stage}";
        $key = ($this->option('rotate-app-key') || ($template['APP_KEY'] ?? '') === '') ? 'base64:'.base64_encode(random_bytes(32)) : $template['APP_KEY'];
        $draft = [
            ...$template,
            'APP_ENV' => 'production', 'APP_KEY' => $key, 'APP_DEBUG' => 'false', 'APP_URL' => "https://{$config->domain}", 'QUEUE_CONNECTION' => 'database',
            'SESSION_COOKIE' => "{$prefix}_session", 'REDIS_PREFIX' => "{$prefix}_database_", 'CACHE_PREFIX' => "{$prefix}_cache_",
            'ACCELERATOR_DEPLOYMENT_KEY' => $config->deploymentKey, 'ACCELERATOR_DEPLOYMENT_STAGE' => $stage, 'ACCELERATOR_DEPLOY_ROOT' => $config->deployRoot,
            'ACCELERATOR_BACKUP_NAME' => "acc-{$config->deploymentKey}-{$stage}", 'OTEL_SERVICE_NAME' => $serviceName, 'OTEL_SERVICE_INSTANCE_ID' => $serviceName,
            'OTEL_RESOURCE_ATTRIBUTES' => "service.namespace=accelerator,deployment.environment.name={$stage},service.instance.id={$serviceName}",
            'OTEL_INSTRUMENTATION_HTTP_SERVER' => 'false', 'OTEL_EXPORTER_OTLP_ENDPOINT' => $template['OTEL_EXPORTER_OTLP_ENDPOINT'] ?? 'https://observe.ohmyserver.com/api/default',
        ];

        if (($draft['ACCELERATOR_FEATURE_REALTIME'] ?? 'false') === 'true') {
            $application = $this->existingOrGeneratedReverbApplication(
                name: "{$config->deploymentKey}-{$stage}",
                origin: "https://{$config->domain}",
                environment: $current,
                rotate: (bool) $this->option('rotate-reverb-app'),
            );
            (new ReverbApplicationRegistry(base_path()))->upsert($application);
            $draft = [...$draft, ...$this->reverbEnvironment($application)];
        }

        $store->replace($path, $draft);

        return $this->success('environment', ($draft['ACCELERATOR_FEATURE_REALTIME'] ?? 'false') === 'true'
            ? [$path, ReverbApplicationRegistry::PATH]
            : [$path]);
    }

    private function localDeploymentKey(): string
    {
        $document = $this->deploymentDocument();
        $key = $document['deployment_key'] ?? basename(base_path());

        return is_string($key) && $key !== '' ? $key : basename(base_path());
    }

    /**
     * @param  array<string, string>  $environment
     * @return array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}
     */
    private function existingOrGeneratedReverbApplication(string $name, string $origin, array $environment, bool $rotate): array
    {
        if (! $rotate
            && ($environment['REVERB_APP_ID'] ?? '') !== ''
            && ($environment['REVERB_APP_KEY'] ?? '') !== ''
            && ($environment['REVERB_APP_SECRET'] ?? '') !== '') {
            $application = ReverbApplicationRegistry::generate($name, $origin);

            return [...$application,
                'app_id' => $environment['REVERB_APP_ID'],
                'key' => $environment['REVERB_APP_KEY'],
                'secret' => $environment['REVERB_APP_SECRET'],
            ];
        }

        return ReverbApplicationRegistry::generate($name, $origin);
    }

    /**
     * @param  array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}  $application
     * @return array<string, string>
     */
    private function reverbEnvironment(array $application): array
    {
        return [
            'REVERB_APP_ID' => $application['app_id'],
            'REVERB_APP_KEY' => $application['key'],
            'REVERB_APP_SECRET' => $application['secret'],
            'REVERB_HOST' => 'centralized-reverb.ohmyserver.com',
            'REVERB_PORT' => '443',
            'REVERB_SCHEME' => 'https',
            'VITE_REVERB_APP_KEY' => $application['key'],
            'VITE_REVERB_HOST' => 'centralized-reverb.ohmyserver.com',
            'VITE_REVERB_PORT' => '443',
            'VITE_REVERB_SCHEME' => 'https',
        ];
    }

    /** @return array<string, mixed> */
    private function deploymentDocument(): array
    {
        $path = base_path('.accelerator/deploy.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $document @throws JsonException */
    private function writeJsonFile(string $path, array $document): void
    {
        $absolute = base_path($path);
        is_dir(dirname($absolute)) || mkdir(dirname($absolute), 0755, true);
        file_put_contents($absolute, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
    }

    private function interactive(): bool
    {
        return $this->input->isInteractive() && ! $this->option('json');
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param list<string> $files */
    private function success(string $scope, array $files): int
    {
        if ($this->option('json')) {
            $this->output->writeln(json_encode(['schema' => 1, 'status' => 'OK', 'scope' => $scope, 'files' => $files], JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Accelerator configuration updated: '.implode(', ', $files));
        }

        return self::SUCCESS;
    }

    private function failure(string $message): int
    {
        if ($this->option('json')) {
            $this->output->writeln(json_encode(['schema' => 1, 'status' => 'ERROR', 'error' => $message], JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
