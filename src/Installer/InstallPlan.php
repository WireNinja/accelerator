<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use InvalidArgumentException;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final readonly class InstallPlan
{
    /** @var list<string> */
    private const FEATURES = [
        'oauth',
        'pwa',
        'telegram',
        'horizon',
        'reverb',
        'scout',
        'nightowl',
    ];

    /**
     * @param  list<string>  $features
     */
    public function __construct(
        public string $appName,
        public string $appUrl,
        public string $adminName,
        public string $adminUsername,
        public string $adminEmail,
        public string $adminPasswordHash,
        public string $packageManager,
        public string $database,
        public bool $useRedis,
        public array $features,
        public bool $deploy,
        public string $deploymentMode,
        public string $deploymentKey,
        public string $sshHost,
        public string $repository,
        public string $repositoryBranch,
        public int $portBase,
        public string $domain,
        public string $deployRoot,
        public string $stagingDomain,
        public string $stagingDeployRoot,
        public string $httpRuntime,
    ) {
        if (! in_array($this->packageManager, ['pnpm', 'npm'], true)) {
            throw new InvalidArgumentException('Package manager must be pnpm or npm.');
        }

        if (trim($this->appName) === '' || preg_match('/[\x00-\x1F\x7F]/', $this->appName) === 1) {
            throw new InvalidArgumentException('Application name is required and cannot contain control characters.');
        }

        $appScheme = parse_url($this->appUrl, PHP_URL_SCHEME);

        if (! filter_var($this->appUrl, FILTER_VALIDATE_URL) || ! in_array($appScheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Application URL must be an absolute HTTP or HTTPS URL.');
        }

        if (trim($this->adminName) === '' || preg_match('/[\x00-\x1F\x7F]/', $this->adminName) === 1 || strlen($this->adminName) > 100) {
            throw new InvalidArgumentException('Super Admin name is required, must be at most 100 bytes, and cannot contain control characters.');
        }

        if (preg_match('/^[a-z0-9._-]+$/', $this->adminUsername) !== 1) {
            throw new InvalidArgumentException('Super Admin username may contain lowercase letters, numbers, dots, underscores, and dashes only.');
        }

        if (! filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Super Admin email must be valid.');
        }

        if (! password_get_info($this->adminPasswordHash)['algo']) {
            throw new InvalidArgumentException('Super Admin password hash is invalid.');
        }

        if (! in_array($this->database, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new InvalidArgumentException('Database must be sqlite, mysql, or pgsql.');
        }

        $unknownFeatures = array_values(array_diff($this->features, self::FEATURES));

        if ($unknownFeatures !== []) {
            throw new InvalidArgumentException('Unknown Accelerator features: '.implode(', ', $unknownFeatures));
        }

        if ($this->deploy && ! in_array($this->httpRuntime, ['fpm', 'octane'], true)) {
            throw new InvalidArgumentException('HTTP runtime must be fpm or octane.');
        }

        if ($this->deploy && ! in_array($this->deploymentMode, ['single', 'dual'], true)) {
            throw new InvalidArgumentException('Deployment mode must be single or dual.');
        }

        if ($this->deploy && ($this->portBase < 1024 || $this->portBase > 65523)) {
            throw new InvalidArgumentException('Deployment port base must be between 1024 and 65523.');
        }

        if ($this->deploy && (
            $this->repository === ''
            || $this->repositoryBranch === ''
            || $this->domain === ''
            || $this->deployRoot === ''
            || ($this->deploymentMode === 'dual' && ($this->stagingDomain === '' || $this->stagingDeployRoot === ''))
        )) {
            throw new InvalidArgumentException('Deployment repository, branch, domain, and enabled stage roots are required.');
        }

        if ($this->deploy) {
            DeploymentConfig::validateInstallerTargets(
                deploymentKey: $this->deploymentKey,
                sshHost: $this->sshHost,
                repository: $this->repository,
                branch: $this->repositoryBranch,
                productionDomain: $this->domain,
                productionRoot: $this->deployRoot,
                stagingDomain: $this->deploymentMode === 'dual' ? $this->stagingDomain : null,
                stagingRoot: $this->deploymentMode === 'dual' ? $this->stagingDeployRoot : null,
            );
        }
    }

    /**
     * @return array<string, array<int, string>|bool|int|string>
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
            adminName: self::string($data, 'adminName'),
            adminUsername: self::string($data, 'adminUsername'),
            adminEmail: self::string($data, 'adminEmail'),
            adminPasswordHash: self::string($data, 'adminPasswordHash'),
            packageManager: self::string($data, 'packageManager', 'pnpm'),
            database: self::string($data, 'database'),
            useRedis: (bool) ($data['useRedis'] ?? false),
            features: array_values(array_filter($data['features'] ?? [], is_string(...))),
            deploy: (bool) ($data['deploy'] ?? false),
            deploymentMode: self::string($data, 'deploymentMode', 'single'),
            deploymentKey: self::string($data, 'deploymentKey', self::string($data, 'project')),
            sshHost: self::string($data, 'sshHost'),
            repository: self::string($data, 'repository'),
            repositoryBranch: self::string($data, 'repositoryBranch', 'main'),
            portBase: self::integer($data, 'portBase', 9010),
            domain: self::string($data, 'domain'),
            deployRoot: self::string($data, 'deployRoot'),
            stagingDomain: self::string($data, 'stagingDomain'),
            stagingDeployRoot: self::string($data, 'stagingDeployRoot'),
            httpRuntime: self::string($data, 'httpRuntime'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key, string $default = ''): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : $default;
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $key, int $default): int
    {
        return is_int($data[$key] ?? null) ? $data[$key] : $default;
    }
}
