<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Illuminate\Foundation\Application;
use RuntimeException;

final readonly class Preflight
{
    /** @var array<string, string> */
    private const PRISTINE_HASHES = [
        'app/Models/User.php' => '1dca1344e88308fe405ae050f02bd65081a649de65ec86f2ee2beabbb4706afa',
        'bootstrap/app.php' => '75b4b9ffab2f26cc796548020402d0c217930f4494fcb8c870b8a1aa0070ff6',
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

    /** @var array<string, string> */
    private const PRISTINE_MIGRATIONS = [
        'database/migrations/0001_01_01_000000_create_users_table.php' => '10d79883bc08ce46510f9ad8b1830b4d06d586dc56ae2c8bac3b3e491f69a90f',
        'database/migrations/0001_01_01_000001_create_cache_table.php' => 'fbb4665e5a977e71df4e74ec75b3c06ca4f17ad0de7138fe9786639189978e77',
        'database/migrations/0001_01_01_000002_create_jobs_table.php' => 'df4910687180313a14a3f96a8bddd808ae145ed1b107cd772caf865c0dc2b746',
    ];

    public function __construct(private InstallContext $context, private Scaffolder $scaffolder) {}

    public function runtime(): void
    {
        foreach (['artisan', 'composer.json', 'vendor/autoload.php'] as $requiredFile) {
            if (! is_file($this->context->projectRoot.'/'.$requiredFile)) {
                throw new RuntimeException("Run the installer from a Laravel project root; missing {$requiredFile}.");
            }
        }

        if (! str_starts_with(Application::VERSION, '13.')) {
            throw new RuntimeException('Accelerator v2 currently supports fresh Laravel 13 projects only.');
        }

        foreach (['composer', $this->context->plan->packageManager] as $command) {
            if (! $this->context->processRunner->commandExists($command)) {
                throw new RuntimeException("Required command is not available: {$command}");
            }
        }

        $manager = $this->context->plan->packageManager;
        $version = $this->context->processRunner->capture([$manager, '--version'], $this->context->projectRoot);
        $minimum = $manager === 'npm' ? '12.0.0' : '11.0.0';

        if (! version_compare($version, $minimum, '>=')) {
            throw new RuntimeException("Accelerator requires {$manager} {$minimum} or newer; found {$version}.");
        }
    }

    public function recipeTargets(): void
    {
        $this->assertPristineFile('resources/js/app.js');

        foreach ($this->scaffolder->recipeFiles() as $source => $relativePath) {
            $target = $this->context->projectRoot.'/'.$relativePath;

            if (! is_file($target)) {
                continue;
            }

            $sourceContents = file_get_contents($source);

            if (! is_string($sourceContents)) {
                throw new RuntimeException("Unable to read recipe file: {$source}");
            }

            $currentHash = hash_file('sha256', $target);
            $targetHash = hash('sha256', $this->scaffolder->render($sourceContents));

            if ($currentHash !== $targetHash && $currentHash !== (self::PRISTINE_HASHES[$relativePath] ?? null)) {
                throw new RuntimeException("Fresh-only installer refused modified file: {$relativePath}");
            }
        }

        foreach (self::PRISTINE_MIGRATIONS as $relativePath => $expectedHash) {
            $path = $this->context->projectRoot.'/'.$relativePath;

            if (is_file($path) && hash_file('sha256', $path) !== $expectedHash) {
                throw new RuntimeException("Fresh-only installer refused modified migration: {$relativePath}");
            }
        }
    }

    public function environmentTargets(): void
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

    private function assertPristineFile(string $relativePath): void
    {
        $path = $this->context->projectRoot.'/'.$relativePath;

        if (is_file($path) && hash_file('sha256', $path) !== (self::PRISTINE_HASHES[$relativePath] ?? null)) {
            throw new RuntimeException("Fresh-only installer refused modified file: {$relativePath}");
        }
    }

    private function environmentFile(string $relativePath): string
    {
        $path = $this->context->projectRoot.'/'.$relativePath;
        $contents = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($contents)) {
            throw new RuntimeException("Fresh-only installer requires readable {$relativePath}.");
        }

        return $contents;
    }
}
