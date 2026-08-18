<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use RuntimeException;

final readonly class InstallContext
{
    public function __construct(
        public string $projectRoot,
        public string $packageRoot,
        public InstallPlan $plan,
        public ProcessRunner $processRunner,
    ) {}

    public function writeFile(string $relativePath, string $contents, int $permissions = 0644): void
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

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->plan->features, true);
    }

    public function boolean(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /** @return list<string> */
    public function packageBinaryArguments(string ...$arguments): array
    {
        return $this->plan->packageManager === 'pnpm'
            ? ['pnpm', 'exec', ...$arguments]
            : ['npm', 'exec', '--', ...$arguments];
    }
}
