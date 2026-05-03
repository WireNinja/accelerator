<?php

namespace WireNinja\Accelerator\Console\Generator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('accelerator:generate-pwa-icons')]
#[Description('Generate PWA icon assets from public/favicon.svg')]
class PwaIconsCommand extends Command
{
    public function handle(): int
    {
        $process = new Process([
            'npx',
            'pwa-assets-generator',
            '--preset',
            'minimal',
            'public/favicon.svg',
        ]);

        $process->setTty(Process::isTtySupported());
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
