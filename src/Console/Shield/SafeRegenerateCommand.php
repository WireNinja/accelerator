<?php

namespace WireNinja\Accelerator\Console\Shield;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use WireNinja\Accelerator\Console\Concerns\HasBanner;

#[Signature('shield:safe-regenerate')]
#[Description('Safe regenerate shield policies and permissions')]
class SafeRegenerateCommand extends Command
{
    use HasBanner;

    public function handle(): void
    {
        $this->displayBanner();

        $this->components->info('Regenerating shield policies and permissions safely...');

        $this->call('shield:generate', [
            '--all' => true,
            '--option' => 'policies_and_permissions',
            '--ignore-existing-policies' => true,
            '--panel' => 'admin',
        ]);

        $this->components->success('Shield regeneration complete!');
    }
}
