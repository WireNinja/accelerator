<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\SshRunner;

final class PortsCommand extends Command
{
    protected $signature = 'accelerator:ports
        {--stage= : Stage used to resolve the default SSH host}
        {--host= : Explicit SSH alias}
        {--range=1024-65535 : Inclusive scan range}
        {--available= : Find the first contiguous block of this size}
        {--json}';

    protected $description = 'Detect active and Accelerator-reserved VPS ports and find a free block';

    /** @throws JsonException */
    public function handle(): int
    {
        try {
            [$minimum, $maximum] = $this->range((string) $this->option('range'));
            $requestedStage = $this->option('stage');
            $config = DeploymentConfig::load(base_path(), is_string($requestedStage) && $requestedStage !== '' ? $requestedStage : null, validateRuntime: false);
            $hostOption = $this->option('host');
            $host = is_string($hostOption) && $hostOption !== '' ? $hostOption : $config->sshHost;
            $output = (new SshRunner(base_path()))->run($host, $this->scanCommand(), 120);
            [$ports, $reserved] = $this->parse($output, $minimum, $maximum);

            if ($host === $config->sshHost) {
                for ($port = $config->portBase; $port < $config->portBase + 20; $port++) {
                    $reserved[$port] = true;

                    if (! isset($ports[$port]) && $port >= $minimum && $port <= $maximum) {
                        $ports[$port] = [
                            'port' => $port,
                            'protocol' => 'tcp',
                            'state' => 'RESERVED',
                            'process' => '',
                            'pid' => '',
                            'source' => 'local .accelerator/deploy.json',
                        ];
                    }
                }

                ksort($ports);
            }
            $blockSize = $this->option('available');
            $available = null;

            if ($blockSize !== null) {
                $size = filter_var($blockSize, FILTER_VALIDATE_INT);

                if (! is_int($size) || $size < 1 || $size > 1000) {
                    throw new RuntimeException('--available must be between 1 and 1000.');
                }

                $available = $this->availableBlock(array_keys($reserved), $minimum, $maximum, $size);
            }

            if ($this->option('json')) {
                $this->output->writeln(json_encode([
                    'schema' => 1,
                    'status' => 'OK',
                    'host' => $host,
                    'range' => [$minimum, $maximum],
                    'ports' => array_values($ports),
                    'available_block' => $available,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } else {
                $this->table(['Port', 'Proto', 'State', 'Process', 'PID', 'Source'], array_map(
                    static fn (array $port): array => [$port['port'], $port['protocol'], $port['state'], $port['process'], $port['pid'], $port['source']],
                    array_values($ports),
                ));

                if (is_array($available)) {
                    $this->components->info("Free block: {$available['start']}-{$available['end']}");
                }
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function scanCommand(): string
    {
        return <<<'BASH'
command sudo -n ss -H -lntup 2>/dev/null | sed 's/^/ACCELERATOR_SOCKET|/'
command sudo -n find /etc/supervisor/conf.d /etc/nginx/sites-enabled -type f -exec grep -HE -- '--port=[0-9]{4,5}|--listen-on=[^ ]+:[0-9]{4,5}|proxy_pass http://[^;]+:[0-9]{4,5}' {} + 2>/dev/null | sed 's/^/ACCELERATOR_CONFIG|/'
command sudo -n find /var/www -path '*/current/.accelerator/deploy.json' -type f -exec grep -HE '"port_base"[[:space:]]*:' {} + 2>/dev/null | sed 's/^/ACCELERATOR_BLOCK|/'
BASH;
    }

    /** @return array{0: int, 1: int} */
    private function range(string $range): array
    {
        if (preg_match('/^(\d+)-(\d+)$/', $range, $matches) !== 1) {
            throw new RuntimeException('--range must use start-end syntax.');
        }

        $minimum = (int) $matches[1];
        $maximum = (int) $matches[2];

        if ($minimum < 1 || $maximum > 65535 || $minimum > $maximum) {
            throw new RuntimeException('--range must be between 1 and 65535.');
        }

        return [$minimum, $maximum];
    }

    /**
     * @return array{0: array<int, array{port: int, protocol: string, state: string, process: string, pid: string, source: string}>, 1: array<int, true>}
     */
    private function parse(string $output, int $minimum, int $maximum): array
    {
        $ports = [];
        $reserved = [];
        $sources = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/ACCELERATOR_BLOCK\|([^:]+):.*"port_base"[[:space:]]*:[[:space:]]*(\d+)/', $line, $matches) === 1) {
                $base = (int) $matches[2];

                for ($port = $base; $port < $base + 20 && $port <= 65535; $port++) {
                    $reserved[$port] = true;
                    $sources[$port][] = 'deploy.json:'.$matches[1];
                }

                continue;
            }

            if (preg_match('/ACCELERATOR_CONFIG\|([^:]+):(.*)$/', $line, $matches) === 1) {
                if (preg_match_all('/(?::|=)(\d{4,5})(?:\D|$)/', $matches[2], $portMatches)) {
                    foreach ($portMatches[1] as $value) {
                        $port = (int) $value;
                        $reserved[$port] = true;
                        $sources[$port][] = $matches[1];
                    }
                }

                continue;
            }

            if (! str_contains($line, 'ACCELERATOR_SOCKET|')) {
                continue;
            }

            $socket = substr($line, strpos($line, 'ACCELERATOR_SOCKET|') + 19);

            if (preg_match('/^(tcp|udp)\s+(\S+)\s+\d+\s+\d+\s+(\S+):(\d+)\s+/', $socket, $matches) !== 1) {
                continue;
            }

            $port = (int) $matches[4];

            if ($port < $minimum || $port > $maximum) {
                continue;
            }

            preg_match('/users:\(\(\"([^\"]+)\",pid=(\d+)/', $socket, $process);
            $reserved[$port] = true;
            $ports[$port] = [
                'port' => $port,
                'protocol' => $matches[1],
                'state' => $matches[2],
                'process' => $process[1] ?? 'unknown',
                'pid' => $process[2] ?? '',
                'source' => '',
            ];
        }

        foreach ($ports as $port => &$entry) {
            $entry['source'] = implode(', ', array_values(array_unique($sources[$port] ?? ['live socket'])));
        }
        unset($entry);

        foreach ($sources as $port => $portSources) {
            if (isset($ports[$port]) || $port < $minimum || $port > $maximum) {
                continue;
            }

            $ports[$port] = [
                'port' => $port,
                'protocol' => 'tcp',
                'state' => 'RESERVED',
                'process' => '',
                'pid' => '',
                'source' => implode(', ', array_values(array_unique($portSources))),
            ];
        }
        ksort($ports);

        return [$ports, $reserved];
    }

    /** @param list<int> $reserved @return array{start: int, end: int}|null */
    private function availableBlock(array $reserved, int $minimum, int $maximum, int $size): ?array
    {
        $used = array_fill_keys($reserved, true);

        for ($start = $minimum; $start + $size - 1 <= $maximum; $start++) {
            for ($port = $start; $port < $start + $size; $port++) {
                if (isset($used[$port])) {
                    $start = $port;

                    continue 2;
                }
            }

            return ['start' => $start, 'end' => $start + $size - 1];
        }

        return null;
    }
}
