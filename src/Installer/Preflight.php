<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Illuminate\Foundation\Application;
use RuntimeException;

final readonly class Preflight
{
    public function __construct(private InstallContext $context) {}

    public function runtime(): void
    {
        foreach (['artisan', 'composer.json', 'vendor/autoload.php'] as $requiredFile) {
            if (! is_file($this->context->projectRoot.'/'.$requiredFile)) {
                throw new RuntimeException("Run the installer from a Laravel project root; missing {$requiredFile}.");
            }
        }

        if (! str_starts_with(Application::VERSION, '13.')) {
            throw new RuntimeException('Accelerator v2 currently supports Laravel 13 applications only.');
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
}
