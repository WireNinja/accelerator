<?php

namespace WireNinja\Accelerator\Console;

use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Console\Concerns\HasBanner;

use function Laravel\Prompts\multiselect;

#[Signature('accelerator:install
    {--force : Overwrite existing files}
    {--dry : Run without making actual changes}
    {--preset=full : Component preset: full, app, or none}
    {--component=* : Component key to install; can be repeated}
    {--without=* : Component key to exclude from the preset}
    {--with-deploy : Generate Envoy deployment files}
    {--with-pwa : Install the Laravel PWA Vite package with Bun}
    {--with-boost : Refresh Laravel Boost resources}
    {--stage-mode=dual : Deployment stage mode: dual or single}
    {--default-stage=test : Default deployment stage}
    {--project= : Deployment project name}
    {--ssh-host=onidel : SSH host alias used by Envoy}
    {--repo= : Git repository SSH URL}
    {--branch=main : Git branch to deploy}
    {--domain= : Single-stage domain}
    {--root= : Single-stage deploy root}
    {--group= : Single-stage Supervisor group}
    {--octane-port= : Single-stage Octane port}
    {--reverb-port= : Single-stage Reverb port}
    {--nightwatch-port= : Single-stage Nightwatch port}
    {--php-bin=php : PHP binary on the VPS}
    {--bun-bin=bun : Bun binary on the VPS}
    {--run-user=www-data : Runtime user for ACL and Supervisor}
    {--ssl-email= : Certbot email address}')]
#[Description('Install WireNinja Accelerator components and optional deployment/PWA scaffolding')]
class InstallCommand extends Command
{
    use HasBanner;

    protected array $allowedOverwrites = [];
    protected bool $envOverwritten = false;

    protected array $wizardComponents = [
        'reverb' => [
            'label' => 'Laravel Reverb (Real-time Broadcaster)',
            'commands' => [['php', 'artisan', 'install:broadcasting', '--reverb', '--without-node']],
            'stubs' => [],
        ],
        'filament-core' => [
            'label' => 'Filament Core (Panels, Widgets)',
            'commands' => [['php', 'artisan', 'filament:install', '--panels', '--force']],
            'stubs' => [
                'app/Models/User.php' => 'app/Models/User.php',
                'app/Providers/Filament/AdminPanelProvider.php' => 'app/Providers/Filament/AdminPanelProvider.php',
            ],
        ],
        'octane' => [
            'label' => 'Laravel Octane (Swoole Performance)',
            'commands' => [['php', 'artisan', 'octane:install', '--server=swoole', '--force']],
            'stubs' => [],
        ],
        'localization' => [
            'label' => 'Localization (Indonesian)',
            'commands' => [
                ['php', 'artisan', 'lang:add', 'id'],
                ['php', 'artisan', 'lang:update'],
            ],
            'stubs' => [],
        ],
        'app-config' => [
            'label' => 'App Core & Service Provider Best Practices',
            'commands' => [],
            'stubs' => [
                'app/Enums/System/ResourceEnum.php' => 'app/Enums/System/ResourceEnum.php',
                'app/Enums/System/RoleEnum.php' => 'app/Enums/System/RoleEnum.php',
                'app/Enums/System/PanelEnum.php' => 'app/Enums/System/PanelEnum.php',
                'bootstrap/app.php' => 'bootstrap/app.php',
                'bootstrap/providers.php' => 'bootstrap/providers.php',
                'routes/console.php' => 'routes/console.php',
            ],
        ],
        'frontend-core' => [
            'label' => 'Frontend Core (Inertia, CSS, Blade, Assets)',
            'commands' => [],
            'stubs' => [
                'resources/css/inertia.css' => 'resources/css/inertia.css',
                'resources/views/app.blade.php' => 'resources/views/app.blade.php',
                'public/favicon.svg' => 'public/favicon.svg',
            ],
        ],
    ];

    public function handle(): void
    {
        $this->displayBanner();

        if ($this->option('dry')) {
            $this->components->warn('DRY RUN MODE ENABLED. No actual changes will be made.');
        }

        $selected = $this->promptForComponents();

        if (empty($selected) && ! $this->hasAddonWork()) {
            return;
        }

        if (! empty($selected)) {
            $this->resolveConflicts($selected);
            $this->syncEnvironment();
            $this->cleanupDefaultMigrations();
            $this->installComponents($selected);
            $this->publishConfigs();
        }

        $this->syncDeploymentFiles();
        $this->installPwaPackage();
        $this->syncBoostResources();

        $this->finalizeInstallation();
    }

    protected function hasAddonWork(): bool
    {
        return (bool) ($this->option('with-deploy') || $this->option('with-pwa') || $this->option('with-boost'));
    }

    protected function resolveConflicts(array $selectedComponents): void
    {
        if ($this->option('force')) {
            return;
        }

        $conflicts = [];

        // Check environment file
        $envDest = base_path('.env.example');
        if (File::exists($envDest)) {
            $conflicts[] = '.env.example';
        }

        // Check configs
        $configSource = __DIR__ . '/../../stubs/config';
        if (File::isDirectory($configSource)) {
            foreach (File::files($configSource) as $file) {
                $targetFile = 'config/' . $file->getFilename();
                if (File::exists(base_path($targetFile))) {
                    $conflicts[] = $targetFile;
                }
            }
        }

        // Check stubs from selected components
        foreach ($selectedComponents as $key) {
            $component = $this->wizardComponents[$key];
            foreach ($component['stubs'] as $targetPath) {
                $dest = base_path($targetPath);
                if (File::exists($dest)) {
                    if ($targetPath === 'app/Models/User.php') {
                        $content = File::get($dest);
                        if (str_contains($content, 'extends Authenticatable') && !str_contains($content, 'AcceleratedUser')) {
                            $this->allowedOverwrites[] = $targetPath;
                            continue; // Auto-overwrite fresh User model
                        }
                    }
                    $conflicts[] = $targetPath;
                }
            }
        }

        if (empty($conflicts)) {
            return;
        }

        $this->newLine();
        $this->components->warn('Some files already exist. Please select which ones to overwrite (default: none):');

        $options = $conflicts;
        array_unshift($options, '[ OVERWRITE ALL ]');

        $selectedToOverwrite = multiselect(
            label: 'Files to overwrite',
            options: $options,
            default: [],
            hint: 'Use space to toggle select, enter to confirm'
        );

        if (in_array('[ OVERWRITE ALL ]', $selectedToOverwrite)) {
            $this->allowedOverwrites = array_merge($this->allowedOverwrites, $conflicts);
        } else {
            $this->allowedOverwrites = array_merge($this->allowedOverwrites, $selectedToOverwrite);
        }
    }

    protected function syncEnvironment(): void
    {
        $this->components->task('Synchronizing environment from .base-env.example', function () {
            $baseEnvPath = __DIR__ . '/../../.base-env.example';
            $examplePath = base_path('.env.example');
            $envPath = base_path('.env');

            if (! File::exists($baseEnvPath)) {
                throw new RuntimeException('.base-env.example not found in accelerator package. Cannot synchronize environment.');
            }

            if (! File::exists($examplePath) || $this->option('force') || in_array('.env.example', $this->allowedOverwrites)) {
                if (File::exists($examplePath)) {
                    $backupPath = base_path('.env.example.backup_' . now()->format('Y_m_d_His'));
                    if ($this->option('dry')) {
                        $this->components->info("Would backup existing .env.example to {$backupPath}");
                    } else {
                        File::copy($examplePath, $backupPath);
                    }
                }

                if ($this->option('dry')) {
                    $this->components->info('Would copy .base-env.example to .env.example');
                } else {
                    File::copy($baseEnvPath, $examplePath);
                }
            }

            if (! File::exists($envPath)) {
                $this->envOverwritten = true;
                if ($this->option('dry')) {
                    $this->components->info('Would copy .base-env.example to .env');
                } else {
                    File::copy($baseEnvPath, $envPath);
                }
            }

            return true;
        });
    }

    protected function cleanupDefaultMigrations(): void
    {
        $this->components->task('Cleaning up default Laravel migrations', function () {
            $migrationsPath = base_path('database/migrations');
            if (! File::isDirectory($migrationsPath)) {
                return;
            }

            $defaults = [
                '0001_01_01_000000_create_users_table.php',
                '0001_01_01_000001_create_cache_table.php',
                '0001_01_01_000002_create_jobs_table.php',
            ];

            foreach ($defaults as $file) {
                $path = $migrationsPath . '/' . $file;
                if (File::exists($path)) {
                    if ($this->option('dry')) {
                        $this->components->info("Would delete default migration: {$file}");
                    } else {
                        File::delete($path);
                    }
                }
            }
        });
    }

    protected function promptForComponents(): array
    {
        $explicitComponents = $this->option('component');

        if (! empty($explicitComponents)) {
            return $this->normalizeComponentSelection($explicitComponents);
        }

        if (! $this->input->isInteractive()) {
            return $this->componentsForPreset((string) $this->option('preset'));
        }

        $selected = multiselect(
            label: 'Which components would you like to install?',
            options: array_map(fn($c) => $c['label'], $this->wizardComponents),
            default: array_keys($this->wizardComponents),
            hint: 'Use space to toggle select, enter to confirm'
        );

        if (empty($selected)) {
            $this->warn('No components selected. Exiting.');
        }

        return $selected;
    }

    protected function componentsForPreset(string $preset): array
    {
        $selected = match ($preset) {
            'full' => array_keys($this->wizardComponents),
            'app' => ['reverb', 'octane', 'localization', 'app-config'],
            'none' => [],
            default => throw new RuntimeException("Unsupported Accelerator install preset [{$preset}]."),
        };

        return $this->normalizeComponentSelection($selected);
    }

    protected function normalizeComponentSelection(array $selected): array
    {
        $without = $this->option('without');
        $valid = array_keys($this->wizardComponents);

        foreach ([...$selected, ...$without] as $component) {
            if (! in_array($component, $valid, true)) {
                throw new RuntimeException("Unknown Accelerator install component [{$component}]. Valid components: " . implode(', ', $valid));
            }
        }

        return array_values(array_diff(array_unique($selected), $without));
    }

    protected function installComponents(array $selected): void
    {
        $this->newLine();
        $this->components->info('Configuring selected components...');
        $this->newLine();

        foreach ($selected as $key) {
            $component = $this->wizardComponents[$key];

            $this->components->task("Configuring {$component['label']}", function () use ($key, $component) {
                foreach ($component['commands'] as $cmd) {
                    if ($key === 'reverb' && $cmd[2] === 'install:broadcasting') {
                        if (File::exists(base_path('config/broadcasting.php'))) {
                            $cmd = ['php', 'artisan', 'reverb:install'];
                        }
                    }

                    if ($this->option('dry')) {
                        $this->components->info('Would run command: ' . implode(' ', $cmd));
                    } else {
                        $this->runProcess($cmd, failOnError: false);
                    }
                }

                foreach ($component['stubs'] as $stubPath => $targetPath) {
                    $source = __DIR__ . '/../../stubs/' . $stubPath;
                    $dest = base_path($targetPath);

                    if (!File::exists($source)) {
                        continue;
                    }

                    if (File::exists($dest) && ! $this->option('force') && ! in_array($targetPath, $this->allowedOverwrites)) {
                        $this->components->warn("Target {$targetPath} already exists. Skipped.");
                        continue;
                    }

                    if ($this->option('dry')) {
                        $action = File::exists($dest) ? 'overwrite existing' : 'create new';
                        $this->components->info("Would {$action}: {$targetPath}");
                        continue;
                    }

                    File::ensureDirectoryExists(dirname($dest));

                    if (File::isDirectory($source)) {
                        File::copyDirectory($source, $dest);
                    } else {
                        File::copy($source, $dest);
                    }
                }
            });
        }
    }

    protected function finalizeInstallation(): void
    {
        $this->newLine();
        $this->components->info('Running final initialization steps...');
        $this->newLine();

        $commands = [];

        if ($this->envOverwritten) {
            $commands[] = ['php', 'artisan', 'key:generate', '--force'];
        }

        // Ensure sqlite database exists if needed
        if (config('database.default') === 'sqlite' || $this->envFileValue('.env', 'DB_CONNECTION') === 'sqlite') {
            $dbPath = database_path('database.sqlite');
            if (! File::exists($dbPath)) {
                if ($this->option('dry')) {
                    $this->components->info('Would create missing database/database.sqlite file');
                } else {
                    File::ensureDirectoryExists(dirname($dbPath));
                    File::put($dbPath, '');
                }
            }
        }

        $commands = array_merge($commands, [
            ['php', 'artisan', 'migrate', '--force'],
            ['php', 'artisan', 'storage:unlink'],
            ['php', 'artisan', 'storage:link', '--force'],
            ['php', 'artisan', 'webpush:vapid', '--force'],
        ]);

        foreach ($commands as $cmd) {
            if ($this->option('dry')) {
                $this->components->info('Would run command: ' . implode(' ', $cmd));
            } else {
                $this->runProcess($cmd, failOnError: false);
            }
        }

        $this->newLine();
        if ($this->option('dry')) {
            $this->components->success('Accelerator Dry Run Complete!');
        } else {
            $this->components->success('Accelerator Installation Complete!');
        }
        $this->newLine();
    }

    protected function syncDeploymentFiles(): void
    {
        if (! $this->option('with-deploy')) {
            return;
        }

        $this->components->task('Preparing Accelerator Envoy deployment files', function () {
            $this->writeFile('Envoy.blade.php', $this->envoyBridgeContent(), overwrite: true);
            $this->writeFile('.env.envoy', $this->envoyEnvContent(), overwrite: $this->option('force'));
            $this->writeFile('.env.staging', $this->runtimeEnvSeedContent('staging'), overwrite: false);
            $this->writeFile('.env.production', $this->runtimeEnvSeedContent('production'), overwrite: false);
            $this->ensureGitignoreEntries([
                '.env.envoy',
                '.env.staging',
                '.env.production',
            ]);

            return true;
        });
    }

    protected function installPwaPackage(): void
    {
        if (! $this->option('with-pwa')) {
            return;
        }

        $this->components->task('Installing Laravel PWA Vite package with Bun', function () {
            $packagePath = base_path('package.json');

            if (! File::exists($packagePath)) {
                $this->components->warn('package.json is missing. Skipping PWA package install.');

                return true;
            }

            if ($this->option('dry')) {
                $this->components->info('Would run command: bun add -d @wireninja/vite-plugin-laravel-pwa vite-plugin-pwa');

                return true;
            }

            $this->runProcess(['bun', 'add', '-d', '@wireninja/vite-plugin-laravel-pwa', 'vite-plugin-pwa'], failOnError: false);

            return true;
        });
    }

    protected function syncBoostResources(): void
    {
        if (! $this->option('with-boost')) {
            return;
        }

        $this->components->task('Refreshing Laravel Boost resources', function () {
            $this->mergeBoostPackageConfig();

            if ($this->option('dry')) {
                $this->components->info('Would run command: php artisan boost:update --ansi');

                return true;
            }

            $this->runProcess(['php', 'artisan', 'boost:update', '--ansi'], failOnError: false);

            return true;
        });
    }

    protected function mergeBoostPackageConfig(): void
    {
        $path = base_path('boost.json');
        $data = File::exists($path) ? json_decode(File::get($path), true) : [];

        if (! is_array($data)) {
            $data = [];
        }

        $data['packages'] = array_values(array_unique([
            ...($data['packages'] ?? []),
            'wireninja/accelerator',
        ]));

        $data['skills'] = array_values(array_unique([
            ...($data['skills'] ?? []),
            'accelerator-deployment',
            'accelerator-env-config',
            'accelerator-filament',
            'accelerator-installation',
            'accelerator-model-outline',
            'accelerator-ops-observability',
            'accelerator-pwa-development',
        ]));

        if ($this->option('dry')) {
            $this->components->info('Would merge wireninja/accelerator into boost.json');

            return;
        }

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    protected function envoyBridgeContent(): string
    {
        $sshHost = $this->option('ssh-host') ?: 'onidel';

        return <<<BLADE
@servers(['vps' => ['{$sshHost}'], 'localhost' => '127.0.0.1'])

@import('vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php')
BLADE . PHP_EOL;
    }

    protected function envoyEnvContent(): string
    {
        $stageMode = (string) $this->option('stage-mode');
        $defaultStage = (string) $this->option('default-stage');

        if (! in_array($stageMode, ['dual', 'single'], true)) {
            throw new RuntimeException('Deployment stage mode must be [dual] or [single].');
        }

        if (! in_array($defaultStage, ['test', 'prod'], true)) {
            throw new RuntimeException('Deployment default stage must be [test] or [prod].');
        }

        $project = $this->option('project') ?: strtolower((string) config('app.name', 'laravel'));
        $domain = $this->option('domain') ?: 'example.com';
        $root = $this->option('root') ?: "/var/www/{$domain}";
        $group = $this->option('group') ?: "{$project}_{$defaultStage}";
        $octanePort = $this->option('octane-port') ?: '9010';
        $reverbPort = $this->option('reverb-port') ?: '9011';
        $nightwatchPort = $this->option('nightwatch-port') ?: '2410';
        $repo = $this->option('repo') ?: 'git@github.com:vendor/project.git';
        $branch = $this->option('branch') ?: 'main';
        $phpBin = $this->option('php-bin') ?: 'php';
        $bunBin = $this->option('bun-bin') ?: 'bun';
        $runUser = $this->option('run-user') ?: 'www-data';
        $sshHost = $this->option('ssh-host') ?: 'onidel';
        $sslEmail = $this->option('ssl-email') ?: "admin@{$domain}";
        $testEnabled = $stageMode === 'dual' || $defaultStage === 'test' ? 'true' : 'false';
        $prodEnabled = $stageMode === 'dual' || $defaultStage === 'prod' ? 'true' : 'false';

        return <<<ENV
# Global
OPS_DEPLOY_DEFAULT_STAGE={$defaultStage}
OPS_DEPLOY_PROJECT={$project}
OPS_DEPLOY_SSH_HOST={$sshHost}
OPS_DEPLOY_REPO={$repo}
OPS_DEPLOY_BRANCH={$branch}
OPS_DEPLOY_KEEP_RELEASES=5
OPS_DEPLOY_PHP_BIN={$phpBin}
OPS_DEPLOY_BUN_BIN={$bunBin}
OPS_DEPLOY_RUN_USER={$runUser}
OPS_DEPLOY_SSL_EMAIL={$sslEmail}

# Test Stage
OPS_DEPLOY_TEST_ENABLED={$testEnabled}
OPS_DEPLOY_TEST_DOMAIN=test.{$domain}
OPS_DEPLOY_TEST_ROOT=/var/www/test.{$domain}
OPS_DEPLOY_TEST_GROUP={$project}_test
OPS_DEPLOY_TEST_RUNTIME=swoole
OPS_DEPLOY_TEST_OCTANE_PORT=9012
OPS_DEPLOY_TEST_REVERB_PORT=9013
OPS_DEPLOY_TEST_NIGHTWATCH_PORT=2412
OPS_DEPLOY_TEST_NIGHTWATCH_ENABLED=false

# Production Stage
OPS_DEPLOY_PROD_ENABLED={$prodEnabled}
OPS_DEPLOY_PROD_DOMAIN={$domain}
OPS_DEPLOY_PROD_ROOT={$root}
OPS_DEPLOY_PROD_GROUP={$group}
OPS_DEPLOY_PROD_RUNTIME=swoole
OPS_DEPLOY_PROD_OCTANE_PORT={$octanePort}
OPS_DEPLOY_PROD_REVERB_PORT={$reverbPort}
OPS_DEPLOY_PROD_NIGHTWATCH_PORT={$nightwatchPort}
OPS_DEPLOY_PROD_NIGHTWATCH_ENABLED=false
ENV . PHP_EOL;
    }

    protected function runtimeEnvSeedContent(string $environment): string
    {
        $source = File::exists(base_path('.env')) ? base_path('.env') : __DIR__ . '/../../.base-env.example';
        $content = File::get($source);

        return str_replace(
            ['APP_ENV=local', 'APP_ENV=production', 'APP_DEBUG=true'],
            ['APP_ENV=' . ($environment === 'production' ? 'production' : 'staging'), 'APP_ENV=' . ($environment === 'production' ? 'production' : 'staging'), 'APP_DEBUG=' . ($environment === 'production' ? 'false' : 'true')],
            $this->stripOpsDeployKeys($content),
        );
    }

    protected function stripOpsDeployKeys(string $content): string
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $kept = array_filter($lines, fn (string $line): bool => ! str_starts_with(trim($line), 'OPS_DEPLOY_'));

        return rtrim(implode(PHP_EOL, $kept)) . PHP_EOL;
    }

    protected function ensureGitignoreEntries(array $entries): void
    {
        $path = base_path('.gitignore');
        $content = File::exists($path) ? File::get($path) : '';
        $lines = preg_split('/\R/', $content) ?: [];

        foreach ($entries as $entry) {
            if (! in_array($entry, $lines, true)) {
                $lines[] = $entry;
            }
        }

        if ($this->option('dry')) {
            $this->components->info('Would ensure deployment env files are ignored in .gitignore');

            return;
        }

        File::put($path, rtrim(implode(PHP_EOL, $lines)) . PHP_EOL);
    }

    protected function writeFile(string $relativePath, string $content, bool $overwrite): void
    {
        $path = base_path($relativePath);

        if (File::exists($path) && ! $overwrite) {
            $this->components->warn("Target {$relativePath} already exists. Skipped.");

            return;
        }

        if ($this->option('dry')) {
            $action = File::exists($path) ? 'overwrite existing' : 'create new';
            $this->components->info("Would {$action}: {$relativePath}");

            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    protected function envFileValue(string $relativePath, string $key): ?string
    {
        $path = base_path($relativePath);

        if (! File::exists($path)) {
            return null;
        }

        foreach (File::lines($path) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$envKey, $value] = explode('=', $line, 2);

            if ($envKey !== $key) {
                continue;
            }

            return trim($value, "\"'");
        }

        return null;
    }

    protected function publishConfigs(): void
    {
        $this->components->task('Publishing internal configurations', function () {
            $source = __DIR__ . '/../../stubs/config';
            $dest = base_path('config');

            if (! File::isDirectory($source)) {
                return;
            }

            foreach (File::files($source) as $file) {
                $targetFile = $dest . '/' . $file->getFilename();

                if (File::exists($targetFile) && ! $this->option('force') && ! in_array('config/' . $file->getFilename(), $this->allowedOverwrites)) {
                    continue;
                }

                if ($this->option('dry')) {
                    $action = File::exists($targetFile) ? 'overwrite existing' : 'create new';
                    $this->components->info("Would {$action} config file: config/" . $file->getFilename());

                    continue;
                }

                File::ensureDirectoryExists($dest);
                File::copy($file->getPathname(), $targetFile);
            }
        });
    }

    protected function runProcess(array $command, bool $failOnError = true): void
    {
        $process = (new Process($command))
            ->setTimeout(null);

        try {
            if (Process::isTtySupported()) {
                $process->setTty(true);
            }
        } catch (Exception $e) {
            // Silence TTY errors
        }

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $message = sprintf(
                'Process [%s] exited with code %d.',
                implode(' ', $command),
                $process->getExitCode(),
            );

            if ($failOnError) {
                throw new RuntimeException($message);
            }
        }
    }
}
