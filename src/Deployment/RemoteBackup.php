<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use JsonException;
use RuntimeException;

final readonly class RemoteBackup
{
    public function __construct(private string $projectRoot) {}

    /**
     * @param  list<string>  $options
     * @return array<string, mixed>
     */
    public function run(string $stage, string $action, array $options = []): array
    {
        $output = (new Deployer($this->projectRoot))->run(
            'accelerator:backup-runtime',
            $stage,
            ["--backup-action={$action}", ...$options],
            capture: true,
        );

        if (preg_match('/ACCELERATOR_BACKUP_RESULT=([A-Za-z0-9+\/=]+)/', $output, $matches) !== 1) {
            throw new RuntimeException('Remote backup operation returned no machine-readable result.');
        }

        $json = base64_decode($matches[1], true);

        if (! is_string($json)) {
            throw new RuntimeException('Remote backup operation returned an invalid result encoding.');
        }

        try {
            $result = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Remote backup operation returned invalid JSON.', previous: $exception);
        }

        if (! is_array($result)) {
            throw new RuntimeException('Remote backup operation returned an invalid result document.');
        }

        if (($result['status'] ?? 'ERROR') === 'ERROR' && $action !== 'status') {
            throw new RuntimeException(is_string($result['error'] ?? null) ? $result['error'] : 'Remote backup operation failed.');
        }

        return $result;
    }
}
