<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use InvalidArgumentException;

final readonly class InstallPlan
{
    /** @var list<string> */
    private const FEATURES = ['oauth', 'pwa', 'telegram', 'realtime', 'scout', 'observability'];

    /**
     * @param  list<string>  $features
     * @param  array<string, array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}>  $reverbApplications
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
        public array $reverbApplications,
    ) {
        if (! in_array($this->packageManager, ['pnpm', 'npm'], true)) {
            throw new InvalidArgumentException('Package manager must be pnpm or npm.');
        }

        if (trim($this->appName) === '' || preg_match('/[\x00-\x1F\x7F]/', $this->appName) === 1) {
            throw new InvalidArgumentException('Application name is required and cannot contain control characters.');
        }

        if (! filter_var($this->appUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($this->appUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Application URL must be an absolute HTTP or HTTPS URL.');
        }

        if (trim($this->adminName) === '' || strlen($this->adminName) > 100) {
            throw new InvalidArgumentException('Super Admin name is required and must be at most 100 bytes.');
        }

        if (preg_match('/^[a-z0-9._-]+$/', $this->adminUsername) !== 1) {
            throw new InvalidArgumentException('Super Admin username may contain lowercase letters, numbers, dots, underscores, and dashes only.');
        }

        if (! filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL) || ! password_get_info($this->adminPasswordHash)['algo']) {
            throw new InvalidArgumentException('Super Admin credentials are invalid.');
        }

        if (! in_array($this->database, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new InvalidArgumentException('Database must be sqlite, mysql, or pgsql.');
        }

        $unknownFeatures = array_values(array_diff($this->features, self::FEATURES));

        if ($unknownFeatures !== []) {
            throw new InvalidArgumentException('Unknown Accelerator features: '.implode(', ', $unknownFeatures));
        }

        if (in_array('realtime', $this->features, true)) {
            if (! self::validReverbApplication($this->reverbApplications['local'] ?? null)) {
                throw new InvalidArgumentException('Centralized Reverb credentials are missing for [local].');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
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
            reverbApplications: is_array($data['reverbApplications'] ?? null) ? $data['reverbApplications'] : [],
        );
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key, string $default = ''): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : $default;
    }

    private static function validReverbApplication(mixed $application): bool
    {
        return is_array($application)
            && is_string($application['name'] ?? null) && $application['name'] !== ''
            && is_string($application['app_id'] ?? null) && $application['app_id'] !== ''
            && is_string($application['key'] ?? null) && $application['key'] !== ''
            && is_string($application['secret'] ?? null) && $application['secret'] !== ''
            && is_array($application['allowed_origins'] ?? null) && $application['allowed_origins'] !== [];
    }
}
