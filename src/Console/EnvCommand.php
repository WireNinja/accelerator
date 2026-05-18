<?php

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use WireNinja\Accelerator\Support\EnvReader;

#[Signature('accelerator:env {--json : Output as JSON} {--compact : Compact JSON output}')]
#[Description('View redacted environment variables from the .env file')]
class EnvCommand extends Command
{
    public function handle(): int
    {
        $data = EnvReader::redacted();

        if ($this->option('json')) {
            return $this->outputJson($data);
        }

        if ($data === []) {
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

    /**
     * @param  array<string, string|int>  $data
     */
    private function outputJson(array $data): int
    {
        $summary = [
            'total' => count($data),
            'redacted' => 0,
            'empty' => 0,
            'missing' => 0,
            'set' => 0,
        ];

        foreach ($data as $value) {
            $bucket = match (true) {
                $value === '[REDACTED]' => 'redacted',
                $value === '[EMPTY]' => 'empty',
                $value === '[MISSING]' => 'missing',
                default => 'set',
            };

            $summary[$bucket]++;
        }

        $payload = [
            'status' => $data === [] ? 'WARNING' : 'OK',
            'summary' => $summary,
            'values' => $data,
        ];

        $flags = ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        $this->output->writeln(json_encode($payload, $flags));

        return $data === [] ? 1 : 0;
    }
}
