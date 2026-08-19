<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Support\Cast;

final readonly class DependencyInstaller
{
    public function __construct(private InstallContext $context) {}

    /** @throws JsonException */
    public function composer(): void
    {
        $path = $this->context->projectRoot.'/composer.json';
        $contents = file_get_contents($path);
        $composer = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;

        if (! is_array($composer)) {
            throw new RuntimeException('Unable to parse project composer.json.');
        }

        $composer = Cast::stringKeyedArray($composer);
        $autoload = Cast::stringKeyedArray(Cast::array($composer['autoload'] ?? null));
        $autoloadFilesValue = $autoload['files'] ?? [];

        if (! is_array($autoloadFilesValue)) {
            throw new RuntimeException('Project composer.json autoload.files must be an array.');
        }

        $autoloadFiles = Cast::stringList($autoloadFilesValue);

        $autoload['files'] = array_values(array_unique([...$autoloadFiles, 'app/Support/helpers.php']));
        $composer['autoload'] = $autoload;

        $extra = Cast::stringKeyedArray(Cast::array($composer['extra'] ?? null));
        $laravel = Cast::stringKeyedArray(Cast::array($extra['laravel'] ?? null));
        unset($laravel['dont-discover']);
        $extra['laravel'] = $laravel;
        $composer['extra'] = $extra;

        $scripts = Cast::stringKeyedArray(Cast::array($composer['scripts'] ?? null));
        $scripts['post-autoload-dump'] = [
            'Illuminate\\Foundation\\ComposerScripts::postAutoloadDump',
            '@php artisan package:discover --ansi',
            '@php artisan filament:upgrade',
        ];
        unset($scripts['dev'], $scripts['analyse']);
        $scripts['format'] = 'pint --format=json';
        $scripts['refactor'] = 'rector --output-format=json';
        $scripts['phpstan'] = 'phpstan analyse --memory-limit=2G --no-progress';
        $composer['scripts'] = $scripts;

        $config = Cast::stringKeyedArray(Cast::array($composer['config'] ?? null));
        $config['sort-packages'] = true;
        $config['policy'] = [
            'advisories' => [
                'block' => true,
                'audit' => 'fail',
            ],
            'malware' => [
                'block' => true,
                'block-scope' => 'all',
                'audit' => 'fail',
            ],
        ];
        $allowPlugins = Cast::stringKeyedArray(Cast::array($config['allow-plugins'] ?? null));
        unset($allowPlugins['wireninja/accelerator']);
        $config['allow-plugins'] = $allowPlugins;
        $composer['config'] = $config;

        $this->context->writeFile('composer.json', json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL);
        $this->installDeveloperDependencies();
    }

    public function frontend(): void
    {
        $this->configureFrontendPolicy();
        $manager = $this->context->plan->packageManager;

        if ($manager === 'pnpm') {
            $this->context->processRunner->run(['pnpm', 'clean', '--lockfile'], $this->context->projectRoot);
        }

        $this->context->processRunner->run([$manager, 'install'], $this->context->projectRoot);

        if ($manager === 'npm') {
            $this->context->processRunner->run(['npm', 'rebuild', 'sharp', '--ignore-scripts=false'], $this->context->projectRoot);
        }

        $this->copyFrontendVendorAssets();
    }

    private function installDeveloperDependencies(): void
    {
        $this->context->processRunner->run([
            'composer',
            'require',
            '--dev',
            'laravel/boost:^2.0',
            'larastan/larastan:^3.0',
            'pestphp/pest:^5.0',
            'pestphp/pest-plugin-laravel:^5.0',
            'phpunit/phpunit:^13.2',
            'rector/rector:^2.0',
            'phpstan/phpstan-deprecation-rules:^2.0',
            '--with-all-dependencies',
            '--no-scripts',
            '--no-interaction',
        ], $this->context->projectRoot);
    }

    private function configureFrontendPolicy(): void
    {
        $manager = $this->context->plan->packageManager;

        // The Laravel skeleton lockfile was resolved before Accelerator's policy existed.
        foreach (['pnpm-lock.yaml', 'package-lock.json', 'yarn.lock'] as $lockFile) {
            $this->deleteIfFile($lockFile);
        }

        if ($manager === 'pnpm') {
            $policy = file_get_contents($this->context->packageRoot.'/resources/install/pnpm-workspace.yaml');

            if (! is_string($policy)) {
                throw new RuntimeException('Unable to read the pnpm supply-chain policy.');
            }

            $this->context->writeFile('pnpm-workspace.yaml', $policy);
            $this->deleteIfFile('.npmrc');

            return;
        }

        $this->context->writeFile('.npmrc', <<<'NPMRC'
min-release-age=7
min-release-age-exclude[]=concurrently
min-release-age-exclude[]=filelist
min-release-age-exclude[]=sharp
package-lock=true
strict-peer-deps=true
ignore-scripts=true
NPMRC);
        $this->deleteIfFile('pnpm-workspace.yaml');
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
            $contents = file_get_contents($this->context->projectRoot.'/node_modules/leaflet/'.$source);

            if (! is_string($contents)) {
                throw new RuntimeException("Unable to read Leaflet asset: {$source}");
            }

            $this->context->writeFile($destination, $contents);
        }
    }

    private function deleteIfFile(string $relativePath): void
    {
        $path = $this->context->projectRoot.'/'.$relativePath;

        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException("Unable to remove conflicting frontend file: {$relativePath}");
        }
    }
}
