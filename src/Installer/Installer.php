<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Illuminate\Foundation\Application;
use JsonException;
use RuntimeException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;

final class Installer
{
    /**
     * Hashes from the pristine Laravel 13.8.0 application skeleton.
     *
     * @var array<string, string>
     */
    private const PRISTINE_HASHES = [
        'app/Models/User.php' => '1dca1344e88308fe405ae050f02bd65081a649de65ec86f2ee2beabbb4706afa',
        'bootstrap/app.php' => '75b4b9ffab2f26cc796548020402d0c217930f4494fcb8c8700b8a1aa0070ff6',
        'bootstrap/providers.php' => 'f720e185207a343d4c19fc99dbb317a4187efbcd7d38a303a695bbdd77ffb943',
        'config/app.php' => '78dcd36b226fd7b24057cb95f567cb6e96561c2406653892d8aef6dcea8461e4',
        'config/auth.php' => 'c7e204e9785c9f596d66fb884b493f658f2327f157646bdb4088efd6b3a7773f',
        'config/cache.php' => 'ee4ad2bba1edfcff52e9599f37371f2370d0b167cc226ce72d46cdf6edf2dc55',
        'config/database.php' => '02cd62f589b43d33f9ceac740705dac1dec9740eb2bb9e6e202d4b66a15244cf',
        'config/filesystems.php' => 'ba7060d7a23e490c3ce830656f6d24ad3ccf5b868e3293f2a12e411959dc5635',
        'config/logging.php' => '299ec1ba5b5a803c8e20f666ae7628e442e856cc2ff7ac9e2adb17b97e8ad63b',
        'config/mail.php' => 'c43ff49a31c5f32ce21eb7159f2a4b3cd2f4074eaed5838d265646dbfedd3474',
        'config/queue.php' => '6101774da7c8b79af46d0028f2b8b31a484c3f7eda10b76e5b27b6093ad364a4',
        'config/services.php' => '8258c4487eb1d73a97e569eb0e411e3f25368044812df4cba807860432be1d92',
        'config/session.php' => '64272cfbef6f47c65bf857f1c5b1e853700c0d0278de46b86663fd83ef4aeed8',
        'database/seeders/DatabaseSeeder.php' => 'cf8e4b6e48360218bb5534f66419c955288f78338eb8a3dba9d8ac3394ec3dbc',
        'package.json' => '10a54d6736b26384ac68636e11f958360e6d18fb9c41e91e05707a918ad58622',
        'resources/css/app.css' => '02db84e827e06d8349293e9797eb8d81465e109e91976775964c4bf873b6fab5',
        'resources/js/app.js' => '101ead936a2281d53dcc064b7e2a2ab0d53b92ef3ef7b34b668673007895c860',
        'routes/console.php' => '9adccc33e7dd400683e434774077c7fdb2f299c5712cedf16a43fdf56f2850fa',
        'vite.config.js' => 'f413e14379e2db8b200c63a6326b925f6d47cc0df458c77bfe188cfc23e8081f',
    ];

    /**
     * @var array<string, string>
     */
    private const DEFAULT_MIGRATIONS = [
        'database/migrations/0001_01_01_000000_create_users_table.php' => '10d79883bc08ce46510f9ad8b1830b4d06d586dc56ae2c8bac3b3e491f69a90f',
        'database/migrations/0001_01_01_000001_create_cache_table.php' => 'fbb4665e5a977e71df4e74ec75b3c06ca4f17ad0de7138fe9786639189978e77',
        'database/migrations/0001_01_01_000002_create_jobs_table.php' => 'df4910687180313a14a3f96a8bddd808ae145ed1b107cd772caf865c0dc2b746',
    ];

    /**
     * @var array<string, string>
     */
    private const RECIPE_FILES = [
        'stubs/app/Enums/System/NavigationGroup.php' => 'app/Enums/System/NavigationGroup.php',
        'stubs/app/Enums/System/PanelEnum.php' => 'app/Enums/System/PanelEnum.php',
        'stubs/app/Enums/System/RoleEnum.php' => 'app/Enums/System/RoleEnum.php',
        'stubs/app/Models/User.php' => 'app/Models/User.php',
        'stubs/app/Providers/Filament/AdminPanelProvider.php' => 'app/Providers/Filament/AdminPanelProvider.php',
        'stubs/app/Support/helpers.php' => 'app/Support/helpers.php',
        'stubs/bootstrap/app.php' => 'bootstrap/app.php',
        'stubs/bootstrap/providers.php.stub' => 'bootstrap/providers.php',
        'stubs/database/seeders/DatabaseSeeder.php' => 'database/seeders/DatabaseSeeder.php',
        'stubs/lang/vendor/filament-panels/id/auth/multi-factor/app/provider.php' => 'lang/vendor/filament-panels/id/auth/multi-factor/app/provider.php',
        'stubs/public/.user.ini' => 'public/.user.ini',
        'stubs/public/favicon.svg' => 'public/favicon.svg',
        'stubs/resources/svg/.gitkeep' => 'resources/svg/.gitkeep',
        'stubs/phpstan.neon' => 'phpstan.neon',
        'stubs/rector.php' => 'rector.php',
        'stubs/routes/console.php' => 'routes/console.php',
        'resources/install/routes/channels.php' => 'routes/channels.php',
        'resources/install/css/app.css' => 'resources/css/app.css',
        'resources/install/css/filament-theme.css' => 'resources/css/filament/theme.css',
        'resources/install/js/app.ts' => 'resources/js/app.ts',
        'resources/install/js/env.d.ts' => 'resources/js/env.d.ts',
        'resources/install/package.json' => 'package.json',
        'resources/install/eslint.config.js' => 'eslint.config.js',
        'resources/install/.prettierignore' => '.prettierignore',
        'resources/install/.prettierrc' => '.prettierrc',
        'resources/install/tsconfig.json' => 'tsconfig.json',
        'resources/install/vite.config.js' => 'vite.config.js',
    ];

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $packageRoot,
        private readonly InstallPlan $plan,
        private readonly ProcessRunner $processRunner,
    ) {}

    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $this->preflightRuntime();
        $journal = new InstallJournal($this->projectRoot, $this->plan);

        if ($journal->isFinished()) {
            outro('Accelerator v2 is already installed with this configuration. Nothing changed.');

            return;
        }

        if (! $journal->isCompleted('scaffold')) {
            $this->validateRecipeTargets();
        }

        if (! $journal->isCompleted('environment')) {
            $this->validateEnvironmentTargets();
        }

        $journal->start();

        $this->step($journal, 'scaffold', function (): void {
            $this->installRecipe();
            $this->removeDefaultMigrations();
        });
        $this->step($journal, 'environment', function (): void {
            $this->writeEnvironmentFiles();
            $this->writeDeploymentFiles();
            $this->updateGitignore();
        });
        $this->step($journal, 'composer', function (): void {
            $this->configureComposer();
            $this->installDeveloperDependencies();
        });
        $this->step($journal, 'frontend', function (): void {
            $this->configureFrontendPolicy();
            $this->processRunner->run([$this->plan->packageManager, 'install'], $this->projectRoot);

            if ($this->plan->packageManager === 'npm') {
                $this->processRunner->run(['npm', 'rebuild', 'sharp', '--ignore-scripts=false'], $this->projectRoot);
            }

            $this->copyFrontendVendorAssets();
        });
        $this->step($journal, 'application', function (): void {
            $this->finalizeApplication();
        });
        $this->step($journal, 'quality', function (): void {
            $this->runQualityTools();
        });

        $journal->finish();

        if ($this->plan->deploy) {
            $firstStage = $this->plan->deploymentMode === 'dual' ? 'staging' : 'production';
            outro("Accelerator v2 installed. Complete .accelerator/environments/{$firstStage}.env, then run php artisan accelerator:configure environment --stage={$firstStage}.");

            return;
        }

        outro('Accelerator v2 installed. Run php artisan accelerator:doctor at any time to verify it.');
    }

    private function preflightRuntime(): void
    {
        foreach (['artisan', 'composer.json', 'vendor/autoload.php'] as $requiredFile) {
            if (! is_file($this->projectRoot.'/'.$requiredFile)) {
                throw new RuntimeException("Run the installer from a Laravel project root; missing {$requiredFile}.");
            }
        }

        if (! str_starts_with(Application::VERSION, '13.')) {
            throw new RuntimeException('Accelerator v2 currently supports fresh Laravel 13 projects only.');
        }

        foreach (['composer', $this->plan->packageManager] as $command) {
            if (! $this->processRunner->commandExists($command)) {
                throw new RuntimeException("Required command is not available: {$command}");
            }
        }

        $packageManagerVersion = $this->processRunner->capture([$this->plan->packageManager, '--version'], $this->projectRoot);
        $minimumVersion = $this->plan->packageManager === 'npm' ? '12.0.0' : '11.0.0';

        if (! version_compare($packageManagerVersion, $minimumVersion, '>=')) {
            throw new RuntimeException("Accelerator requires {$this->plan->packageManager} {$minimumVersion} or newer; found {$packageManagerVersion}.");
        }
    }

    private function installRecipe(): void
    {
        foreach ($this->recipeFiles() as $source => $destination) {
            $contents = file_get_contents($source);

            if (! is_string($contents)) {
                throw new RuntimeException("Unable to read recipe file: {$source}");
            }

            $this->writeFile($destination, $this->render($contents));
        }

        $legacyPaths = ['resources/js/app.js'];

        foreach ($legacyPaths as $legacyPath) {
            $path = $this->projectRoot.'/'.$legacyPath;

            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException("Unable to remove replaced Laravel file: {$legacyPath}");
            }
        }
    }

    private function configureFrontendPolicy(): void
    {
        $selectedLock = $this->plan->packageManager === 'pnpm' ? 'pnpm-lock.yaml' : 'package-lock.json';

        foreach (['pnpm-lock.yaml', 'package-lock.json', 'yarn.lock'] as $lockFile) {
            if ($lockFile === $selectedLock) {
                continue;
            }

            $path = $this->projectRoot.'/'.$lockFile;

            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException("Unable to remove conflicting frontend lock: {$lockFile}");
            }
        }

        if ($this->plan->packageManager === 'pnpm') {
            $this->writeFile('pnpm-workspace.yaml', <<<'YAML'
minimumReleaseAge: 10080
minimumReleaseAgeExclude:
  - concurrently
  - filelist
  - sharp
minimumReleaseAgeIgnoreMissingTime: false
overrides:
  filelist: ^2.0.2
  sharp: ^0.35.3
strictDepBuilds: true
allowBuilds:
  'sharp@0.35.3': true
blockExoticSubdeps: true
trustPolicy: no-downgrade
trustPolicyExclude:
  - '@trickfilm400/rollup-plugin-off-main-thread@3.0.0-pre1'
  - 'semver@6.3.1'
YAML);

            $npmConfig = $this->projectRoot.'/.npmrc';

            if (is_file($npmConfig) && ! unlink($npmConfig)) {
                throw new RuntimeException('Unable to remove npm-only policy file: .npmrc');
            }

            return;
        }

        $this->writeFile('.npmrc', <<<'NPMRC'
min-release-age=7
min-release-age-exclude[]=concurrently
min-release-age-exclude[]=filelist
min-release-age-exclude[]=sharp
package-lock=true
strict-peer-deps=true
ignore-scripts=true
NPMRC);

        $pnpmConfig = $this->projectRoot.'/pnpm-workspace.yaml';

        if (is_file($pnpmConfig) && ! unlink($pnpmConfig)) {
            throw new RuntimeException('Unable to remove pnpm-only policy file: pnpm-workspace.yaml');
        }
    }

    private function copyFrontendVendorAssets(): void
    {
        foreach ([
            'dist/leaflet.js' => 'resources/vendor/accelerator/leaflet/leaflet.js',
            'dist/leaflet.css' => 'resources/vendor/accelerator/leaflet/leaflet.css',
            'dist/images/layers.png' => 'resources/vendor/accelerator/leaflet/images/layers.png',
            'dist/images/layers-2x.png' => 'resources/vendor/accelerator/leaflet/images/layers-2x.png',
            'dist/images/marker-icon.png' => 'resources/vendor/accelerator/leaflet/images/marker-icon.png',
            'dist/images/marker-icon-2x.png' => 'resources/vendor/accelerator/leaflet/images/marker-icon-2x.png',
            'dist/images/marker-shadow.png' => 'resources/vendor/accelerator/leaflet/images/marker-shadow.png',
        ] as $source => $destination) {
            $contents = file_get_contents($this->projectRoot.'/node_modules/leaflet/'.$source);

            if (! is_string($contents)) {
                throw new RuntimeException("Unable to read Leaflet asset: {$source}");
            }

            $this->writeFile($destination, $contents);
        }
    }

    private function removeDefaultMigrations(): void
    {
        foreach (self::DEFAULT_MIGRATIONS as $relativePath => $expectedHash) {
            $path = $this->projectRoot.'/'.$relativePath;

            if (! is_file($path)) {
                continue;
            }

            if (hash_file('sha256', $path) !== $expectedHash) {
                throw new RuntimeException("Refusing to delete modified migration: {$relativePath}");
            }

            if (! unlink($path)) {
                throw new RuntimeException("Unable to remove Laravel migration: {$relativePath}");
            }
        }

        if ($this->plan->database === 'sqlite') {
            $databasePath = $this->projectRoot.'/database/database.sqlite';

            if (! is_file($databasePath) && touch($databasePath) === false) {
                throw new RuntimeException('Unable to create database/database.sqlite.');
            }
        }
    }

    private function writeEnvironmentFiles(): void
    {
        $template = file_get_contents($this->packageRoot.'/.base-env.example');

        if (! is_string($template)) {
            throw new RuntimeException('Unable to read Accelerator environment template.');
        }

        $currentEnvironment = file_get_contents($this->projectRoot.'/.env');
        $appKey = is_string($currentEnvironment) ? $this->environmentValue($currentEnvironment, 'APP_KEY') : '';
        $appKey = $appKey !== '' ? $appKey : 'base64:'.base64_encode(random_bytes(32));
        $runtime = $this->renderEnvironment($template, $appKey);
        $example = $this->renderEnvironment($template, '');

        $this->writeFile('.env', $runtime, 0600);
        $this->writeFile('.env.example', $example);
    }

    private function writeDeploymentFiles(): void
    {
        if (! $this->plan->deploy) {
            return;
        }

        $stage = fn (bool $enabled, string $domain, int $octanePort, int $reverbPort, int $nightwatchPort): array => [
            'enabled' => $enabled,
            'ssh_host' => $this->plan->sshHost,
            'domain' => $domain,
            'root' => $domain === '' ? '' : "/var/www/{$domain}",
            'http_runtime' => $this->plan->httpRuntime,
            'horizon' => $this->hasFeature('horizon'),
            'queue_worker' => ! $this->hasFeature('horizon'),
            'reverb' => $this->hasFeature('reverb'),
            'nightwatch' => $this->hasFeature('nightwatch'),
            'scheduler' => true,
            'octane_port' => $octanePort,
            'reverb_port' => $reverbPort,
            'nightwatch_port' => $nightwatchPort,
            'health_path' => '/up',
        ];
        $document = [
            'schema' => 1,
            'default_stage' => $this->plan->deploymentMode === 'dual' ? 'staging' : 'production',
            'project' => $this->plan->project,
            'repository' => $this->plan->repository,
            'branch' => $this->plan->repositoryBranch,
            'keep_releases' => 5,
            'php_version' => '8.5',
            'php_binary' => 'php8.5',
            'package_manager' => $this->plan->packageManager,
            'run_user' => 'www-data',
            'ssl_email' => $this->plan->adminEmail,
            'stages' => [
                'staging' => $stage($this->plan->deploymentMode === 'dual', $this->plan->stagingDomain, 8100, 8180, 2507),
                'production' => $stage(true, $this->plan->domain, 8000, 8080, 2407),
            ],
        ];
        $this->writeFile('.accelerator/deploy.json', json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL);

        $runtime = file_get_contents($this->projectRoot.'/.env.example');

        if (is_string($runtime)) {
            if ($this->plan->deploymentMode === 'dual') {
                $this->writeFile('.accelerator/environments/staging.env', $this->productionEnvironment(
                    $runtime,
                    'staging',
                    $this->plan->stagingDomain,
                    $this->plan->stagingDeployRoot,
                ), 0600);
            }

            $this->writeFile('.accelerator/environments/production.env', $this->productionEnvironment(
                $runtime,
                'production',
                $this->plan->domain,
                $this->plan->deployRoot,
            ), 0600);
        }
    }

    private function configureComposer(): void
    {
        $path = $this->projectRoot.'/composer.json';
        $contents = file_get_contents($path);
        $composer = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;

        if (! is_array($composer)) {
            throw new RuntimeException('Unable to parse project composer.json.');
        }

        $autoloadFiles = $composer['autoload']['files'] ?? [];

        if (! is_array($autoloadFiles)) {
            throw new RuntimeException('Project composer.json autoload.files must be an array.');
        }

        $composer['autoload']['files'] = array_values(array_unique([
            ...$autoloadFiles,
            'app/Support/helpers.php',
        ]));

        $dontDiscover = $composer['extra']['laravel']['dont-discover'] ?? [];

        if (! is_array($dontDiscover)) {
            throw new RuntimeException('Project composer.json extra.laravel.dont-discover must be an array.');
        }

        $composer['extra']['laravel']['dont-discover'] = array_values(array_unique([
            ...$dontDiscover,
            'laravel/horizon',
        ]));

        $composer['scripts']['post-autoload-dump'] = [
            'Illuminate\\Foundation\\ComposerScripts::postAutoloadDump',
            '@php artisan package:discover --ansi',
            '@php artisan filament:upgrade',
        ];
        $composer['scripts']['dev'] = [
            'Composer\\Config::disableProcessTimeout',
            $this->packageBinaryCommand('concurrently').' -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" "php artisan serve" "php artisan queue:listen --tries=1 --timeout=0" "php artisan pail --timeout=0" "'.$this->packageScriptCommand('dev').'" --names=server,queue,logs,vite --kill-others',
        ];
        $composer['scripts']['format'] = 'pint --format=json';
        $composer['scripts']['refactor'] = 'rector --output-format=json';
        $composer['scripts']['phpstan'] = 'phpstan analyse --memory-limit=2G --no-progress';
        unset($composer['scripts']['analyse']);
        $composer['config']['sort-packages'] = true;
        unset($composer['config']['allow-plugins']['wireninja/accelerator']);

        $payload = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $this->writeFile('composer.json', $payload);
    }

    private function installDeveloperDependencies(): void
    {
        $this->processRunner->run([
            'composer',
            'require',
            '--dev',
            'laravel/boost:^2.0',
            'deployer/deployer:^8.0',
            'larastan/larastan:^3.0',
            'pestphp/pest:^5.0',
            'pestphp/pest-plugin-laravel:^5.0',
            'phpunit/phpunit:^13.2',
            'rector/rector:^2.0',
            'phpstan/phpstan-deprecation-rules:^2.0',
            '--with-all-dependencies',
            '--no-scripts',
            '--no-interaction',
        ], $this->projectRoot);
    }

    private function finalizeApplication(): void
    {
        $this->processRunner->run(['composer', 'dump-autoload', '--no-interaction'], $this->projectRoot);
        $this->processRunner->run(['php', 'artisan', 'lang:add', 'id', '--no-interaction'], $this->projectRoot);
        $this->processRunner->run(['php', 'artisan', 'lang:update', '--no-interaction'], $this->projectRoot);
        $this->processRunner->run(['php', 'artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction'], $this->projectRoot);
        $this->processRunner->run(['php', 'artisan', 'storage:link', '--force', '--no-interaction'], $this->projectRoot);

        $this->processRunner->run(['php', 'artisan', 'shield:safe-regenerate', '--no-interaction'], $this->projectRoot);

        $this->processRunner->run([
            'php',
            'artisan',
            'accelerator:provision-admin',
            '--name='.$this->plan->adminName,
            '--username='.$this->plan->adminUsername,
            '--email='.$this->plan->adminEmail,
            '--password-hash='.$this->plan->adminPasswordHash,
            '--no-interaction',
        ], $this->projectRoot);

        if ($this->hasFeature('pwa')) {
            $this->processRunner->run($this->packageBinaryArguments('laravel-pwa', 'icons'), $this->projectRoot);
        }

        $this->processRunner->run([$this->plan->packageManager, 'run', 'build'], $this->projectRoot);
    }

    private function runQualityTools(): void
    {
        $this->selectAcceleratorBoostResources();
        $this->processRunner->run([
            'php',
            'artisan',
            'boost:install',
            '--ansi',
            '--no-interaction',
        ], $this->projectRoot);
        $this->assertAcceleratorBoostResources();
        $this->processRunner->run(['vendor/bin/pint', '--format=agent'], $this->projectRoot);
        $this->processRunner->run(['composer', 'phpstan'], $this->projectRoot);
        $this->processRunner->run([$this->plan->packageManager, 'run', 'lint:check'], $this->projectRoot);
        $this->processRunner->run([$this->plan->packageManager, 'run', 'format:check'], $this->projectRoot);
        $this->processRunner->run([$this->plan->packageManager, 'run', 'types:check'], $this->projectRoot);
        $this->processRunner->run(['php', 'artisan', 'accelerator:doctor'], $this->projectRoot);
    }

    private function selectAcceleratorBoostResources(): void
    {
        $path = $this->projectRoot.'/boost.json';
        $contents = is_file($path) ? file_get_contents($path) : null;
        $config = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : [];

        if (! is_array($config)) {
            throw new RuntimeException('Unable to parse project boost.json.');
        }

        $packages = $config['packages'] ?? [];

        if (! is_array($packages)) {
            throw new RuntimeException('Project boost.json packages must be an array.');
        }

        $config['packages'] = array_values(array_unique([
            ...$packages,
            'wireninja/accelerator',
        ]));
        ksort($config);

        $payload = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $this->writeFile('boost.json', $payload);
    }

    private function assertAcceleratorBoostResources(): void
    {
        $path = $this->projectRoot.'/boost.json';
        $contents = file_get_contents($path);
        $config = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;
        $installedSkills = is_array($config) ? ($config['skills'] ?? null) : null;

        if (! is_array($installedSkills)) {
            throw new RuntimeException('Boost did not record installed skills.');
        }

        $skillFiles = glob($this->packageRoot.'/resources/boost/skills/*/SKILL.md') ?: [];
        $expectedSkills = array_map(static fn (string $skillFile): string => basename(dirname($skillFile)), $skillFiles);
        $missingSkills = array_values(array_diff($expectedSkills, $installedSkills));

        if ($missingSkills !== []) {
            throw new RuntimeException('Boost did not install Accelerator skills: '.implode(', ', $missingSkills));
        }
    }

    private function renderEnvironment(string $template, string $appKey): string
    {
        $cacheDriver = $this->plan->useRedis ? 'redis' : 'database';
        $queueDriver = $this->plan->useRedis ? 'redis' : 'database';
        $databaseName = str_replace('-', '_', $this->plan->project);
        $values = [
            'APP_NAME' => '"'.addcslashes($this->plan->appName, '"\\').'"',
            'APP_KEY' => $appKey,
            'APP_URL' => $this->plan->appUrl,
            'DB_CONNECTION' => $this->plan->database,
            'CACHE_STORE' => $cacheDriver,
            'SESSION_DRIVER' => $cacheDriver,
            'SESSION_STORE' => $cacheDriver,
            'QUEUE_CONNECTION' => $queueDriver,
            'BROADCAST_CONNECTION' => $this->hasFeature('reverb') ? 'reverb' : 'log',
            'SCOUT_DRIVER' => $this->hasFeature('scout') ? 'database' : 'collection',
            'NIGHTWATCH_ENABLED' => $this->boolean($this->hasFeature('nightwatch')),
            'ACCELERATOR_FEATURE_OAUTH' => $this->boolean($this->hasFeature('oauth')),
            'ACCELERATOR_FEATURE_PWA' => $this->boolean($this->hasFeature('pwa')),
            'ACCELERATOR_FEATURE_TELEGRAM' => $this->boolean($this->hasFeature('telegram')),
            'ACCELERATOR_FEATURE_HORIZON' => $this->boolean($this->hasFeature('horizon')),
            'ACCELERATOR_OAUTH_MODE' => $this->hasFeature('oauth') ? 'existing_only' : 'disabled',
            'ACCELERATOR_UPLOAD_MAX_MB' => '100',
            'ACCELERATOR_UI_DENSITY' => 'compact',
            'GOOGLE_REDIRECT_URI' => rtrim($this->plan->appUrl, '/').'/auth/google/callback',
            'VITE_APP_NAME' => '"'.addcslashes($this->plan->appName, '"\\').'"',
        ];

        if ($this->hasFeature('reverb') && $appKey !== '') {
            $reverbKey = bin2hex(random_bytes(16));
            $values += [
                'REVERB_APP_ID' => bin2hex(random_bytes(8)),
                'REVERB_APP_KEY' => $reverbKey,
                'REVERB_APP_SECRET' => bin2hex(random_bytes(32)),
                'VITE_REVERB_APP_KEY' => $reverbKey,
            ];
        }

        if ($this->plan->database !== 'sqlite') {
            $values += [
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => $this->plan->database === 'pgsql' ? '5432' : '3306',
                'DB_DATABASE' => $databaseName,
                'DB_USERNAME' => $databaseName,
                'DB_PASSWORD' => '',
            ];
        }

        foreach ($values as $key => $value) {
            $template = $this->setEnvironmentValue($template, $key, $value);
        }

        return rtrim($template).PHP_EOL;
    }

    private function productionEnvironment(string $contents, string $stage, string $domain, string $deployRoot): string
    {
        $contents = $this->setEnvironmentValue($contents, 'APP_ENV', 'production');
        $contents = $this->setEnvironmentValue($contents, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        $contents = $this->setEnvironmentValue($contents, 'APP_DEBUG', 'false');
        $contents = $this->setEnvironmentValue($contents, 'APP_URL', "https://{$domain}");
        $contents = $this->setEnvironmentValue($contents, 'LOG_LEVEL', 'error');

        if ($this->plan->database === 'sqlite') {
            $contents = $this->setEnvironmentValue($contents, 'DB_DATABASE', rtrim($deployRoot, '/').'/shared/database/database.sqlite');
        } else {
            $database = str_replace('-', '_', $this->plan->project).'_'.$stage;
            $contents = $this->setEnvironmentValue($contents, 'DB_DATABASE', $database);
        }

        if ($this->hasFeature('reverb')) {
            $reverbKey = bin2hex(random_bytes(16));
            $contents = $this->setEnvironmentValue($contents, 'REVERB_APP_ID', bin2hex(random_bytes(8)));
            $contents = $this->setEnvironmentValue($contents, 'REVERB_APP_KEY', $reverbKey);
            $contents = $this->setEnvironmentValue($contents, 'REVERB_APP_SECRET', bin2hex(random_bytes(32)));
            $contents = $this->setEnvironmentValue($contents, 'REVERB_HOST', $domain);
            $contents = $this->setEnvironmentValue($contents, 'REVERB_PORT', '443');
            $contents = $this->setEnvironmentValue($contents, 'REVERB_SCHEME', 'https');
            $contents = $this->setEnvironmentValue($contents, 'VITE_REVERB_APP_KEY', $reverbKey);
            $contents = $this->setEnvironmentValue($contents, 'VITE_REVERB_HOST', $domain);
            $contents = $this->setEnvironmentValue($contents, 'VITE_REVERB_PORT', '443');
            $contents = $this->setEnvironmentValue($contents, 'VITE_REVERB_SCHEME', 'https');
        }

        foreach (['DB_PASSWORD', 'GOOGLE_CLIENT_SECRET', 'NIGHTWATCH_TOKEN', 'TELEGRAM_BOT_TOKEN', 'VAPID_PRIVATE_KEY'] as $key) {
            $contents = $this->setEnvironmentValue($contents, $key, '');
        }

        return $contents;
    }

    private function setEnvironmentValue(string $contents, string $key, string $value): string
    {
        $pattern = '/^(?:#\s*)?'.preg_quote($key, '/').'=.*$/m';
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace_callback($pattern, static fn (): string => $replacement, $contents, 1);
        }

        return rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
    }

    private function environmentValue(string $contents, string $key): string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return '';
        }

        return trim($matches[1], " \t\n\r\0\x0B\"");
    }

    private function updateGitignore(): void
    {
        $path = $this->projectRoot.'/.gitignore';
        $contents = is_file($path) ? file_get_contents($path) : '';

        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read .gitignore.');
        }

        foreach (['/.accelerator/install-state.json', '/.accelerator/environments/'] as $entry) {
            if (! preg_match('/^'.preg_quote($entry, '/').'$/m', $contents)) {
                $contents = rtrim($contents).PHP_EOL.$entry.PHP_EOL;
            }
        }

        $this->writeFile('.gitignore', $contents);
    }

    private function validateRecipeTargets(): void
    {
        foreach (['resources/js/app.js'] as $legacyPath) {
            $path = $this->projectRoot.'/'.$legacyPath;

            if (is_file($path) && hash_file('sha256', $path) !== self::PRISTINE_HASHES[$legacyPath]) {
                throw new RuntimeException("Fresh-only installer refused modified file: {$legacyPath}");
            }
        }

        foreach ($this->recipeFiles() as $source => $relativePath) {
            $target = $this->projectRoot.'/'.$relativePath;

            if (! is_file($target)) {
                continue;
            }

            $sourceContents = file_get_contents($source);

            if (! is_string($sourceContents)) {
                throw new RuntimeException("Unable to read recipe file: {$source}");
            }

            $currentHash = hash_file('sha256', $target);
            $targetHash = hash('sha256', $this->render($sourceContents));

            if ($currentHash === $targetHash || $currentHash === (self::PRISTINE_HASHES[$relativePath] ?? null)) {
                continue;
            }

            throw new RuntimeException("Fresh-only installer refused modified file: {$relativePath}");
        }

        foreach (self::DEFAULT_MIGRATIONS as $relativePath => $expectedHash) {
            $path = $this->projectRoot.'/'.$relativePath;

            if (is_file($path) && hash_file('sha256', $path) !== $expectedHash) {
                throw new RuntimeException("Fresh-only installer refused modified migration: {$relativePath}");
            }
        }
    }

    private function validateEnvironmentTargets(): void
    {
        $environment = $this->environmentFile('.env');
        $example = $this->environmentFile('.env.example');
        $environmentIsAccelerated = str_contains($environment, 'ACCELERATOR_UPLOAD_MAX_MB=');
        $exampleIsAccelerated = str_contains($example, 'ACCELERATOR_UPLOAD_MAX_MB=');

        if ($environmentIsAccelerated || $exampleIsAccelerated) {
            if (! $environmentIsAccelerated || ! $exampleIsAccelerated) {
                throw new RuntimeException('Accelerator environment setup is incomplete; both .env and .env.example must be managed together.');
            }

            return;
        }

        $normalize = static function (string $contents): string {
            $contents = (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=', $contents);
            $contents = (string) preg_replace('/^APP_URL=.*$/m', 'APP_URL=', $contents);

            return rtrim($contents);
        };

        if ($normalize($environment) !== $normalize($example)) {
            throw new RuntimeException('Fresh-only installer refused customized .env; only Laravel\'s generated APP_KEY and APP_URL differences are accepted.');
        }
    }

    private function environmentFile(string $relativePath): string
    {
        $path = $this->projectRoot.'/'.$relativePath;

        if (! is_file($path)) {
            throw new RuntimeException("Fresh-only installer requires {$relativePath}.");
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException("Unable to read {$relativePath}.");
        }

        return $contents;
    }

    /**
     * @return array<string, string>
     */
    private function recipeFiles(): array
    {
        $files = [];

        foreach (self::RECIPE_FILES as $source => $destination) {
            $files[$this->packageRoot.'/'.$source] = $destination;
        }

        $configPaths = glob($this->packageRoot.'/stubs/config/*.php') ?: [];
        sort($configPaths);

        foreach ($configPaths as $configPath) {
            $files[$configPath] = 'config/'.basename($configPath);
        }

        return $files;
    }

    private function render(string $contents): string
    {
        $providers = [
            '    AppServiceProvider::class,',
        ];
        $providerImports = [
            'use App\\Providers\\AppServiceProvider;',
        ];

        $providerImports[] = 'use App\\Providers\\Filament\\AdminPanelProvider;';
        $providers[] = '    AdminPanelProvider::class,';

        return strtr($contents, [
            '{{ app_name }}' => $this->plan->appName,
            '{{ package_manager_spec }}' => $this->packageManagerSpecification(),
            '{{ provider_imports }}' => implode(PHP_EOL, $providerImports),
            '{{ providers }}' => implode(PHP_EOL, $providers),
            '{{ pwa_enabled }}' => $this->boolean($this->hasFeature('pwa')),
        ]);
    }

    /**
     * @return list<string>
     */
    private function packageBinaryArguments(string ...$arguments): array
    {
        return $this->plan->packageManager === 'pnpm'
            ? ['pnpm', 'exec', ...$arguments]
            : ['npm', 'exec', '--', ...$arguments];
    }

    private function packageBinaryCommand(string $binary): string
    {
        return implode(' ', $this->packageBinaryArguments($binary));
    }

    private function packageScriptCommand(string $script): string
    {
        return "{$this->plan->packageManager} run {$script}";
    }

    private function packageManagerSpecification(): string
    {
        $version = $this->processRunner->capture([$this->plan->packageManager, '--version'], $this->projectRoot);

        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new RuntimeException("Unable to resolve an exact {$this->plan->packageManager} version.");
        }

        return "{$this->plan->packageManager}@{$version}";
    }

    private function writeFile(string $relativePath, string $contents, int $permissions = 0644): void
    {
        $path = $this->projectRoot.'/'.$relativePath;
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        $temporaryPath = $path.'.accelerator-tmp';

        if (
            file_put_contents($temporaryPath, $contents, LOCK_EX) === false
            || ! chmod($temporaryPath, $permissions)
            || ! rename($temporaryPath, $path)
        ) {
            throw new RuntimeException("Unable to write file: {$relativePath}");
        }
    }

    private function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->plan->features, true);
    }

    private function boolean(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * @param  callable(): void  $callback
     *
     * @throws JsonException
     */
    private function step(InstallJournal $journal, string $name, callable $callback): void
    {
        if ($journal->isCompleted($name)) {
            info("Skipping completed step: {$name}");

            return;
        }

        info("Installing: {$name}");
        $callback();
        $journal->complete($name);
    }
}
