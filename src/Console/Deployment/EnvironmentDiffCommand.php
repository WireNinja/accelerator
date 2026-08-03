<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Configuration\EnvironmentStore;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;
use WireNinja\Accelerator\Deployment\SshRunner;

final class EnvironmentDiffCommand extends Command
{
    protected $signature = 'accelerator:env:diff {--stage=production} {--json}';

    protected $description = 'Compare local canonical and remote stage environments without printing values';

    /** @throws JsonException */
    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            $environment = new DeploymentEnvironment(base_path());
            $environment->assertValid($config);
            $local = $environment->read($config);
            $remoteContents = (new SshRunner(base_path()))->run(
                $config->sshHost,
                'cat '.escapeshellarg($config->sharedPath().'/.env'),
            );
            $remote = (new EnvironmentStore(base_path()))->parse($remoteContents, "remote {$stage} environment");
            $keys = array_values(array_unique([...array_keys($local), ...array_keys($remote)]));
            sort($keys);
            $differences = [];

            foreach ($keys as $key) {
                $state = match (true) {
                    ! array_key_exists($key, $local) => 'remote_only',
                    ! array_key_exists($key, $remote) => 'local_only',
                    hash_equals($local[$key], $remote[$key]) => 'equal',
                    default => 'changed',
                };

                if ($state !== 'equal') {
                    $differences[$key] = $state;
                }
            }

            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'schema' => 1,
                    'status' => $differences === [] ? 'OK' : 'DRIFT',
                    'stage' => $stage,
                    'differences' => $differences,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } elseif ($differences === []) {
                $this->components->info("Local and remote {$stage} environments match.");
            } else {
                $this->table(['Key', 'State'], array_map(
                    static fn (string $key, string $state): array => [$key, $state],
                    array_keys($differences),
                    array_values($differences),
                ));
            }

            return $differences === [] ? self::SUCCESS : 2;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
