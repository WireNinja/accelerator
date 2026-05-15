<?php

namespace WireNinja\Accelerator\Console\Generator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('accelerator:generate-pwa-icons {source=public/favicon.svg : Source icon path} {--preset=minimal : @vite-pwa/assets-generator preset}')]
#[Description('Generate PWA icon assets from public/favicon.svg')]
class PwaIconsCommand extends Command
{
    public function handle(): int
    {
        $localBinary = base_path('node_modules/.bin/laravel-pwa');
        $process = new Process(file_exists($localBinary)
            ? [$localBinary, 'icons', '--source', $this->argument('source'), '--preset', $this->option('preset')]
            : ['npx', '--yes', '@wireninja/vite-plugin-laravel-pwa', 'icons', '--source', $this->argument('source'), '--preset', $this->option('preset')]
        );

        $process->setTty(Process::isTtySupported());
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
