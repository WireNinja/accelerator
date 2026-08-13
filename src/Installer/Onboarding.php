<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Configuration\ReverbApplicationRegistry;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class Onboarding
{
    /**
     * @var array<string, string>
     */
    private const FEATURES = [
        'oauth' => 'Google OAuth (safe default: existing users only)',
        'pwa' => 'Progressive Web App assets',
        'telegram' => 'Telegram notification channel',
        'realtime' => 'Realtime broadcasting through centralized Reverb',
        'scout' => 'Scout search with the database driver',
        'observability' => 'OpenTelemetry export to centralized OpenObserve',
    ];

    /** @var list<string> */
    private const DEFAULT_FEATURES = [
        'pwa',
        'scout',
    ];

    public function __construct(private readonly string $projectRoot) {}

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
            default: 'http://localhost:8000',
            required: true,
            validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL)
                ? null
                : 'Enter a valid absolute URL.',
        );
        $adminName = text(
            label: 'Initial Super Admin name',
            default: 'Super Administrator',
            required: true,
        );
        $adminUsername = text(
            label: 'Initial Super Admin username',
            default: 'superadmin',
            required: true,
            validate: static fn (string $value): ?string => preg_match('/^[a-z0-9._-]+$/', $value) === 1
                ? null
                : 'Use lowercase letters, numbers, dots, underscores, or dashes.',
        );
        $adminEmail = text(
            label: 'Initial Super Admin email',
            default: 'admin@example.com',
            required: true,
            validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : 'Enter a valid email address.',
        );
        $adminPassword = password(
            label: 'Initial Super Admin password',
            required: true,
            validate: $this->validatePassword(...),
            hint: 'At least 12 characters with upper/lowercase letters, a number, and a symbol.',
        );
        password(
            label: 'Confirm Super Admin password',
            required: true,
            validate: static fn (string $value): ?string => hash_equals($adminPassword, $value)
                ? null
                : 'Passwords do not match.',
        );
        $packageManager = select(
            label: 'Frontend package manager',
            options: [
                'pnpm' => 'pnpm (recommended)',
                'npm' => 'npm',
            ],
            default: 'pnpm',
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
            label: 'Use Redis for cache and sessions?',
            default: false,
            hint: 'The queue always uses the database driver.',
        );
        $features = $this->resolveFeatures(multiselect(
            label: 'Activate optional runtime features',
            options: self::FEATURES,
            default: self::DEFAULT_FEATURES,
            hint: 'Filament, settings, and RBAC are always installed. External-service integrations remain optional.',
            required: false,
        ));
        $reverbApplications = $this->reverbApplications(
            features: $features,
            deploymentKey: $defaultProject,
            localUrl: $appUrl,
        );

        if ($reverbApplications !== []) {
            note('Accelerator generated isolated Reverb credentials for each runtime. Register the ignored .accelerator/reverb-apps.json entries on the centralized Reverb server before connecting.');
        }

        return new InstallPlan(
            appName: $appName,
            appUrl: $appUrl,
            adminName: trim($adminName),
            adminUsername: strtolower(trim($adminUsername)),
            adminEmail: strtolower(trim($adminEmail)),
            adminPasswordHash: $this->hashPassword($adminPassword),
            packageManager: $packageManager,
            database: $database,
            useRedis: $useRedis,
            features: $features,
            reverbApplications: $reverbApplications,
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
        $features = self::DEFAULT_FEATURES;

        if (isset($options['features']) && is_string($options['features'])) {
            $requestedFeatures = array_values(array_filter(explode(',', $options['features'])));
            $unknownFeatures = array_values(array_diff($requestedFeatures, array_keys(self::FEATURES)));

            if ($unknownFeatures !== []) {
                throw new RuntimeException('Unknown Accelerator features: '.implode(', ', $unknownFeatures));
            }

            $features = $this->resolveFeatures($requestedFeatures);
        }
        $adminPassword = $this->option($options, 'admin-password', (string) getenv('ACCELERATOR_ADMIN_PASSWORD'));
        if ($adminPassword === '') {
            throw new RuntimeException('Non-interactive installation requires --admin-password or ACCELERATOR_ADMIN_PASSWORD. The password is never generated or printed.');
        }

        if ($error = $this->validatePassword($adminPassword)) {
            throw new RuntimeException($error);
        }

        return new InstallPlan(
            appName: $this->option($options, 'app-name', Str::headline($directoryName)),
            appUrl: $this->option($options, 'app-url', 'http://localhost:8000'),
            adminName: trim($this->option($options, 'admin-name', 'Super Administrator')),
            adminUsername: strtolower(trim($this->option($options, 'admin-username', 'superadmin'))),
            adminEmail: strtolower(trim($this->option($options, 'admin-email', 'admin@example.com'))),
            adminPasswordHash: $this->hashPassword($adminPassword),
            packageManager: $this->option($options, 'package-manager', 'pnpm'),
            database: $this->option($options, 'database', 'sqlite'),
            useRedis: isset($options['redis']),
            features: $features,
            reverbApplications: $this->reverbApplications(
                features: $features,
                deploymentKey: $defaultProject,
                localUrl: $this->option($options, 'app-url', 'http://localhost:8000'),
            ),
        );
    }

    /**
     * @param  list<string>  $features
     * @return list<string>
     */
    private function resolveFeatures(array $features): array
    {
        $selected = array_fill_keys($features, true);

        return array_values(array_filter(
            array_keys(self::FEATURES),
            static fn (string $feature): bool => isset($selected[$feature]),
        ));
    }

    /**
     * @param  list<string>  $features
     * @return array<string, array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}>
     */
    private function reverbApplications(
        array $features,
        string $deploymentKey,
        string $localUrl,
    ): array {
        if (! in_array('realtime', $features, true)) {
            return [];
        }

        return [
            'local' => ReverbApplicationRegistry::generate("{$deploymentKey}-local", $localUrl),
        ];
    }

    private function validatePassword(string $password): ?string
    {
        $isValid = strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;

        return $isValid
            ? null
            : 'Super Admin password must contain at least 12 characters, upper/lowercase letters, a number, and a symbol.';
    }

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
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
