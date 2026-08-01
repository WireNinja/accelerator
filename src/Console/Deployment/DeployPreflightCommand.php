<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Deployment\Deployer;

final class DeployPreflightCommand extends Command
{
    protected $signature = 'accelerator:deploy:preflight {--stage=production} {--json}';

    protected $description = 'Check deployment ownership, service names, and listening ports without changing the server';

    /** @throws JsonException */
    public function handle(): int
    {
        $stage = (string) $this->option('stage');

        try {
            $output = (new Deployer(base_path()))->run('accelerator:preflight', $stage, capture: true);
            $preflight = $this->parsePreflight($output);

            if ($this->option('json')) {
                $this->writeJson('OK', $stage, $preflight);
            } else {
                $this->table(['Field', 'Value'], array_map(
                    static fn (string $key, string $value): array => [$key, $value],
                    array_keys($preflight),
                    array_values($preflight),
                ));
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            if ($this->option('json')) {
                $this->writeJson('ERROR', $stage, [], $exception->getMessage());
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    /** @return array<string, string> */
    private function parsePreflight(string $output): array
    {
        $preflight = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/ACCELERATOR_PREFLIGHT ([a-z_]+)=(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $preflight[$matches[1]] = trim($matches[2]);
        }

        if ($preflight === []) {
            throw new RuntimeException('Deployment preflight returned no machine-readable fields.');
        }

        return $preflight;
    }

    /**
     * @param  array<string, string>  $preflight
     *
     * @throws JsonException
     */
    private function writeJson(string $status, string $stage, array $preflight, ?string $error = null): void
    {
        $this->output->writeln(json_encode(array_filter([
            'schema' => 1,
            'status' => $status,
            'stage' => $stage,
            'preflight' => $preflight,
            'error' => $error,
        ], static fn (mixed $value): bool => $value !== null), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
