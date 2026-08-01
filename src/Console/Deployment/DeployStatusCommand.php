<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Deployment\Deployer;

final class DeployStatusCommand extends Command
{
    protected $signature = 'accelerator:deploy:status {--stage=production} {--json}';

    protected $description = 'Read deployment state without changing the server';

    /** @throws JsonException */
    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $output = (new Deployer(base_path()))->run('accelerator:status', $stage, capture: true);
            $status = $this->parseStatus($output);

            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'schema' => 1,
                    'status' => ($status['health'] ?? null) === 'ok' ? 'OK' : 'WARNING',
                    'stage' => $stage,
                    'deployment' => $status,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } else {
                $this->table(['Field', 'Value'], array_map(
                    static fn (string $key, string $value): array => [$key, $value],
                    array_keys($status),
                    array_values($status),
                ));
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string, string> */
    private function parseStatus(string $output): array
    {
        $status = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/ACCELERATOR_STATUS ([a-z_]+)=(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $status[$matches[1]] = trim($matches[2]);
        }

        if ($status === []) {
            throw new RuntimeException('Deployment status returned no machine-readable fields.');
        }

        return $status;
    }
}
