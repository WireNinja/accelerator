<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use InvalidArgumentException;

final readonly class InstallPlan
{
    /**
     * @param  list<string>  $features
     */
    public function __construct(
        public string $appName,
        public string $appUrl,
        public string $primaryFrontend,
        public string $database,
        public bool $useRedis,
        public array $features,
        public bool $deploy,
        public string $project,
        public string $sshHost,
        public string $repository,
        public string $domain,
        public string $deployRoot,
        public string $httpRuntime,
    ) {
        if (! in_array($this->primaryFrontend, ['inertia', 'livewire'], true)) {
            throw new InvalidArgumentException('Primary frontend must be inertia or livewire.');
        }

        if (! in_array($this->database, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new InvalidArgumentException('Database must be sqlite, mysql, or pgsql.');
        }

        if (! in_array($this->httpRuntime, ['fpm', 'octane'], true)) {
            throw new InvalidArgumentException('HTTP runtime must be fpm or octane.');
        }

        if ($this->deploy && ($this->repository === '' || $this->domain === '' || $this->deployRoot === '')) {
            throw new InvalidArgumentException('Deployment repository, domain, and root are required.');
        }
    }

    /**
     * @return array<string, array<int, string>|bool|string>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            appName: self::string($data, 'appName'),
            appUrl: self::string($data, 'appUrl'),
            primaryFrontend: self::string($data, 'primaryFrontend'),
            database: self::string($data, 'database'),
            useRedis: (bool) ($data['useRedis'] ?? false),
            features: array_values(array_filter($data['features'] ?? [], is_string(...))),
            deploy: (bool) ($data['deploy'] ?? false),
            project: self::string($data, 'project'),
            sshHost: self::string($data, 'sshHost'),
            repository: self::string($data, 'repository'),
            domain: self::string($data, 'domain'),
            deployRoot: self::string($data, 'deployRoot'),
            httpRuntime: self::string($data, 'httpRuntime'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : '';
    }
}
