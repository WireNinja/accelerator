<?php

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use WireNinja\Accelerator\Support\EnvReader;

#[Signature('accelerator:env')]
#[Description('View redacted environment variables from the .env file')]
class EnvCommand extends Command
{
    public function handle(): int
    {
        $data = EnvReader::redacted();

        if (empty($data)) {
            $this->components->warn('.env file not found or empty.');

            return 1;
        }

        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [$key, $value];
        }

        $this->table(['Key', 'Value'], $rows);

        return 0;
    }
}
