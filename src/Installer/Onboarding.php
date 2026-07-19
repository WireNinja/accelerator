<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class Onboarding
{
    /**
     * @var array<string, string>
     */
    private const FEATURES = [
        'filament' => 'Filament defaults and Accelerator components',
        'fortify' => 'Fortify authentication backend',
        'panels' => 'Built-in System and Support panels',
        'settings' => 'Application settings UI',
        'ticketing' => 'Internal ticketing',
        'oauth' => 'Google OAuth (existing users only)',
        'pwa' => 'Progressive Web App assets',
        'telegram' => 'Telegram notifications',
        'telemetry' => 'Swoole exception telemetry',
        'insider' => 'Authenticated diagnostics dashboard',
        'horizon' => 'Horizon queue dashboard and supervisor',
        'reverb' => 'Reverb real-time broadcasting',
        'scout' => 'Scout search with the database driver',
        'nightwatch' => 'Nightwatch observability collector',
        'wayfinder' => 'Wayfinder typed frontend routes',
    ];

    /** @var list<string> */
    private const DEFAULT_FEATURES = [
        'filament',
        'fortify',
        'panels',
        'settings',
        'ticketing',
        'pwa',
        'insider',
        'scout',
        'wayfinder',
    ];

    public function __construct(
        private readonly string $projectRoot,
        private readonly ProcessRunner $processRunner,
    ) {}

    /**
     * @param  list<string>  $arguments
     *
     * @throws JsonException
     */
    public function plan(array $arguments): InstallPlan
    {
        if ($savedPlan = $this->savedPlan()) {
            return $savedPlan;
        }

        $options = $this->parseArguments($arguments);
        $interactive = ! isset($options['no-interaction']);
        $directoryName = basename($this->projectRoot);
        $defaultProject = Str::slug($directoryName);

        if (! $interactive) {
            return $this->nonInteractivePlan($options, $directoryName, $defaultProject);
        }

        intro('WireNinja Accelerator v2');

        $appName = text(
            label: 'Application name',
            default: Str::headline($directoryName),
            required: true,
        );
        $appUrl = text(
            label: 'Local application URL',
            default: "http://{$defaultProject}.test",
            required: true,
            validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL)
                ? null
                : 'Enter a valid absolute URL.',
        );
        $primaryFrontend = select(
            label: 'Primary landing frontend',
            options: [
                'inertia' => 'Inertia v3 + Vue 3',
                'livewire' => 'Livewire v4',
            ],
            default: 'inertia',
        );
        $database = select(
            label: 'Database',
            options: [
                'sqlite' => 'SQLite (zero configuration)',
                'mysql' => 'MySQL / MariaDB',
                'pgsql' => 'PostgreSQL',
            ],
            default: 'sqlite',
        );
        $useRedis = confirm(
            label: 'Use Redis for cache, sessions, and queues?',
            default: false,
            hint: 'Database drivers work immediately without an external service.',
        );
        $features = multiselect(
            label: 'Activate runtime features',
            options: self::FEATURES,
            default: self::DEFAULT_FEATURES,
            hint: 'Dependencies stay installed even when a feature is inactive.',
            required: true,
        );
        $deploy = confirm(
            label: 'Configure staging and production deployment now?',
            default: true,
        );

        $project = $defaultProject;
        $sshHost = 'onidel';
        $repository = $this->processRunner->capture(['git', 'remote', 'get-url', 'origin'], $this->projectRoot);
        $domain = (string) parse_url($appUrl, PHP_URL_HOST);
        $deployRoot = "/var/www/{$project}";
        $httpRuntime = 'octane';

        if ($deploy) {
            $project = text(label: 'Deployment project key', default: $project, required: true);
            $sshHost = text(label: 'SSH host alias', default: $sshHost, required: true);
            $repository = text(label: 'Git repository URL', default: $repository, required: true);
            $domain = text(
                label: 'Production domain',
                default: str_ends_with($domain, '.test') ? '' : $domain,
                required: true,
                validate: static fn (string $value): ?string => preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9.-]+(?<!-)$/i', $value)
                    ? null
                    : 'Enter a valid hostname without a scheme or path.',
            );
            $deployRoot = text(label: 'Production release root', default: "/var/www/{$domain}", required: true);
            $httpRuntime = select(
                label: 'Production HTTP runtime',
                options: ['octane' => 'Octane + Swoole', 'fpm' => 'PHP-FPM'],
                default: 'octane',
            );
        }

        return new InstallPlan(
            appName: $appName,
            appUrl: $appUrl,
            primaryFrontend: $primaryFrontend,
            database: $database,
            useRedis: $useRedis,
            features: array_values($features),
            deploy: $deploy,
            project: $project,
            sshHost: $sshHost,
            repository: $repository,
            domain: $domain,
            deployRoot: $deployRoot,
            httpRuntime: $httpRuntime,
        );
    }

    /**
     * @throws JsonException
     */
    private function savedPlan(): ?InstallPlan
    {
        $path = $this->projectRoot.'/.accelerator/install-state.json';

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        $state = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;

        if (! is_array($state) || ! is_array($state['plan'] ?? null)) {
            throw new RuntimeException('The existing Accelerator install journal is invalid.');
        }

        if (($state['finished'] ?? false) !== true) {
            note('Resuming the unfinished Accelerator installation.');
        }

        return InstallPlan::fromArray($state['plan']);
    }

    /**
     * @param  array<string, string|true>  $options
     */
    private function nonInteractivePlan(array $options, string $directoryName, string $defaultProject): InstallPlan
    {
        $features = isset($options['features']) && is_string($options['features'])
            ? array_values(array_intersect(explode(',', $options['features']), array_keys(self::FEATURES)))
            : self::DEFAULT_FEATURES;
        $deploy = isset($options['deploy']);
        $domain = $this->option($options, 'domain');

        return new InstallPlan(
            appName: $this->option($options, 'app-name', Str::headline($directoryName)),
            appUrl: $this->option($options, 'app-url', "http://{$defaultProject}.test"),
            primaryFrontend: $this->option($options, 'frontend', 'inertia'),
            database: $this->option($options, 'database', 'sqlite'),
            useRedis: isset($options['redis']),
            features: $features,
            deploy: $deploy,
            project: $this->option($options, 'project', $defaultProject),
            sshHost: $this->option($options, 'ssh-host', 'onidel'),
            repository: $this->option($options, 'repo'),
            domain: $domain,
            deployRoot: $this->option($options, 'deploy-root', $domain === '' ? '' : "/var/www/{$domain}"),
            httpRuntime: $this->option($options, 'http-runtime', 'octane'),
        );
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, string|true>
     */
    private function parseArguments(array $arguments): array
    {
        $options = [];

        foreach ($arguments as $argument) {
            if (! str_starts_with($argument, '--')) {
                throw new RuntimeException("Unexpected installer argument: {$argument}");
            }

            $option = substr($argument, 2);

            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                $options[$key] = $value;
            } else {
                $options[$option] = true;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, string|true>  $options
     */
    private function option(array $options, string $key, string $default = ''): string
    {
        return is_string($options[$key] ?? null) ? $options[$key] : $default;
    }
}
