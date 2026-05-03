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

#[Signature('accelerator:install {--force : Overwrite existing files} {--dry : Run without making actual changes}')]
#[Description('Fully interactive wizard for Laravel/Filament Accelerator installation')]
class InstallCommand extends Command
{
    use HasBanner;

    protected array $wizardComponents = [
        'reverb' => [
            'label' => 'Laravel Reverb (Real-time Broadcaster)',
            'commands' => [['php', 'artisan', 'install:broadcasting', '--reverb', '--without-node', '--force']],
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
                ['php', 'artisan', 'lang:add', 'id', '--force'],
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
            ],
        ],
    ];

    public function handle(): void
    {
        $this->displayBanner();

        if ($this->option('dry')) {
            $this->components->warn('DRY RUN MODE ENABLED. No actual changes will be made.');
        }

        $this->prepareEnvironment();

        $selected = $this->promptForComponents();

        if (empty($selected)) {
            return;
        }

        $this->installComponents($selected);

        $this->finalizeInstallation();
    }

    protected function prepareEnvironment(): void
    {
        $this->syncEnvironment();
        $this->publishConfigs();
    }

    protected function syncEnvironment(): void
    {
        $this->components->task('Synchronizing environment from .base-env.example to .env.example', function () {
            $baseEnvPath = __DIR__.'/../../.base-env.example';
            $examplePath = base_path('.env.example');

            if (! File::exists($baseEnvPath)) {
                throw new RuntimeException('.base-env.example not found in accelerator package. Cannot synchronize environment.');
            }

            if (File::exists($examplePath) && ! $this->option('force')) {
                return false;
            }

            if (File::exists($examplePath)) {
                $backupPath = base_path('.env.example.backup_'.now()->format('Y_m_d_His'));
                if ($this->option('dry')) {
                    $this->components->info("Would backup existing .env.example to {$backupPath}");
                } else {
                    File::copy($examplePath, $backupPath);
                }
            }

            if ($this->option('dry')) {
                $this->components->info('Would copy .base-env.example to .env.example');

                return true;
            }

            File::copy($baseEnvPath, $examplePath);
        });
    }

    protected function promptForComponents(): array
    {
        $selected = multiselect(
            label: 'Which components would you like to install?',
            options: array_map(fn ($c) => $c['label'], $this->wizardComponents),
            default: array_keys($this->wizardComponents),
            hint: 'Use space to toggle select, enter to confirm'
        );

        if (empty($selected)) {
            $this->warn('No components selected. Exiting.');
        }

        return $selected;
    }

    protected function installComponents(array $selected): void
    {
        $this->newLine();
        $this->components->info('Configuring selected components...');
        $this->newLine();

        foreach ($selected as $key) {
            $component = $this->wizardComponents[$key];

            $this->components->task("Configuring {$component['label']}", function () use ($component) {
                foreach ($component['commands'] as $cmd) {
                    if ($this->option('dry')) {
                        $this->components->info('Would run command: '.implode(' ', $cmd));
                    } else {
                        $this->runProcess($cmd, failOnError: false);
                    }
                }

                foreach ($component['stubs'] as $stubPath => $targetPath) {
                    $source = __DIR__.'/../../stubs/'.$stubPath;
                    $dest = base_path($targetPath);

                    if (File::exists($dest) && ! $this->option('force')) {
                        $this->components->warn("File {$targetPath} already exists. Use --force to overwrite.");

                        continue;
                    }

                    if ($this->option('dry')) {
                        $action = File::exists($dest) ? 'overwrite existing' : 'create new';
                        $this->components->info("Would {$action} file: {$targetPath}");

                        continue;
                    }

                    File::ensureDirectoryExists(dirname($dest));
                    File::copy($source, $dest);
                }
            });
        }
    }

    protected function finalizeInstallation(): void
    {
        $this->newLine();
        $this->components->info('Running final initialization steps...');
        $this->newLine();

        $commands = [
            ['php', 'artisan', 'migrate', '--force'],
            ['php', 'artisan', 'storage:unlink'],
            ['php', 'artisan', 'storage:link', '--force'],
            ['php', 'artisan', 'webpush:vapid', '--force'],
        ];

        foreach ($commands as $cmd) {
            if ($this->option('dry')) {
                $this->components->info('Would run command: '.implode(' ', $cmd));
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

    protected function publishConfigs(): void
    {
        $this->components->task('Publishing internal configurations', function () {
            $source = __DIR__.'/../../stubs/config';
            $dest = base_path('config');

            if (! File::isDirectory($source)) {
                return;
            }

            foreach (File::files($source) as $file) {
                $targetFile = $dest.'/'.$file->getFilename();

                if (File::exists($targetFile) && ! $this->option('force')) {
                    continue;
                }

                if ($this->option('dry')) {
                    $action = File::exists($targetFile) ? 'overwrite existing' : 'create new';
                    $this->components->info("Would {$action} config file: config/".$file->getFilename());

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
