<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final readonly class DeploymentConfig
{
    /** @param array<string, mixed> $document */
    private function __construct(
        public string $projectRoot,
        public string $stage,
        public string $deploymentKey,
        public string $sshHost,
        public string $repository,
        public string $branch,
        public int $keepReleases,
        public string $phpVersion,
        public string $phpBinary,
        public string $packageManager,
        public string $runUser,
        public string $sslEmail,
        public string $domain,
        public string $deployRoot,
        public string $group,
        public bool $dnsDirect,
        public string $fpmSocket,
        public string $fpmService,
        public string $healthPath,
        public array $document,
    ) {}

    /** @throws JsonException */
    public static function load(string $projectRoot, ?string $requestedStage = null, bool $validateRuntime = true): self
    {
        $projectRoot = rtrim($projectRoot, '/');
        self::assertNoLegacyFiles($projectRoot);
        $contents = @file_get_contents($projectRoot.'/.accelerator/deploy.json');

        if (! is_string($contents)) {
            throw new RuntimeException('Deployment is not configured. Run: php artisan accelerator:configure deployment');
        }

        $document = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($document) || ($document['schema'] ?? null) !== 3) {
            throw new RuntimeException('deploy.json must use Accelerator deployment schema 3. Re-run accelerator:configure deployment.');
        }

        self::validateTopologyDocument($document);
        $stage = $requestedStage ?: self::string($document, 'default_stage');
        $stageConfig = $document['stages'][$stage] ?? null;

        if (! is_array($stageConfig) || ($stageConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException("Deployment stage [{$stage}] is not enabled.");
        }

        $phpVersion = self::string($document, 'php_version', '8.5');
        $config = new self(
            projectRoot: $projectRoot,
            stage: $stage,
            deploymentKey: self::string($document, 'deployment_key'),
            sshHost: self::string($stageConfig, 'ssh_host'),
            repository: self::string($document, 'repository'),
            branch: self::string($document, 'branch', 'main'),
            keepReleases: self::integer($document, 'keep_releases', 5),
            phpVersion: $phpVersion,
            phpBinary: self::string($document, 'php_binary', "php{$phpVersion}"),
            packageManager: self::string($document, 'package_manager', 'pnpm'),
            runUser: self::string($document, 'run_user', 'www-data'),
            sslEmail: self::string($document, 'ssl_email'),
            domain: self::string($stageConfig, 'domain'),
            deployRoot: rtrim(self::string($stageConfig, 'root'), '/'),
            group: self::serviceGroup(self::string($document, 'deployment_key'), $stage),
            dnsDirect: self::bool($stageConfig, 'dns_direct', true),
            fpmSocket: self::string($stageConfig, 'fpm_socket', "/run/php/php{$phpVersion}-fpm.sock"),
            fpmService: self::string($stageConfig, 'fpm_service', "php{$phpVersion}-fpm"),
            healthPath: self::string($stageConfig, 'health_path', '/up'),
            document: $document,
        );

        if ($validateRuntime) {
            (new DeploymentEnvironment($projectRoot))->assertValid($config);
        }

        return $config;
    }

    public function runtimeEnvironmentFile(): string
    {
        return $this->projectRoot.'/.accelerator/environments/'.$this->stage.'.env';
    }

    public function sharedPath(): string
    {
        return $this->deployRoot.'/shared';
    }

    public function currentPath(): string
    {
        return $this->deployRoot.'/current';
    }

    public function cronName(): string
    {
        return self::serviceGroup($this->deploymentKey, $this->stage);
    }

    public function ownerToken(string $kind): string
    {
        return implode(' ', ['WireNinja-Accelerator', "kind={$kind}", "deployment={$this->deploymentKey}", "stage={$this->stage}", "domain={$this->domain}", "root={$this->deployRoot}"]);
    }

    public static function serviceGroup(string $deploymentKey, string $stage): string
    {
        return "acc-{$deploymentKey}-{$stage}";
    }

    /** @param array<string, mixed> $document */
    public static function validateTopologyDocument(array $document): void
    {
        if (($document['schema'] ?? null) !== 3) {
            throw new InvalidArgumentException('Deployment schema must equal 3.');
        }

        $deploymentKey = self::string($document, 'deployment_key');
        self::validateDeploymentKey($deploymentKey);
        $stages = $document['stages'] ?? null;

        if (! is_array($stages)) {
            throw new InvalidArgumentException('Deployment stages must be an object.');
        }

        $enabled = [];
        $domains = [];
        $roots = [];

        foreach (['staging', 'production'] as $stage) {
            $stageConfig = $stages[$stage] ?? null;

            if (! is_array($stageConfig) || ($stageConfig['enabled'] ?? false) !== true) {
                continue;
            }

            $host = self::string($stageConfig, 'ssh_host');
            $domain = self::string($stageConfig, 'domain');
            $root = rtrim(self::string($stageConfig, 'root', "/var/www/{$domain}"), '/');
            self::validateDomainRoot($domain, $root);

            if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $host) !== 1) {
                throw new InvalidArgumentException("SSH host for {$stage} must be an alias from ~/.ssh/config.");
            }

            if (isset($domains[$domain]) || isset($roots[$root])) {
                throw new InvalidArgumentException('Enabled stages must use distinct domains and deployment roots.');
            }

            $enabled[] = $stage;
            $domains[$domain] = true;
            $roots[$root] = true;
        }

        if ($enabled === [] || ! in_array(self::string($document, 'default_stage'), $enabled, true)) {
            throw new InvalidArgumentException('default_stage must identify an enabled deployment stage.');
        }
    }

    public static function validateInstallerTargets(string $deploymentKey, string $sshHost, string $repository, string $branch, string $productionDomain, string $productionRoot, ?string $stagingDomain = null, ?string $stagingRoot = null): void
    {
        self::validateDeploymentKey($deploymentKey);

        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $sshHost) !== 1 || $repository === '' || preg_match('/\s/', $repository.$branch) === 1) {
            throw new InvalidArgumentException('SSH host, repository, and branch are invalid.');
        }

        self::validateDomainRoot($productionDomain, $productionRoot);

        if ($stagingDomain !== null && $stagingRoot !== null) {
            self::validateDomainRoot($stagingDomain, $stagingRoot);

            if ($stagingDomain === $productionDomain || $stagingRoot === $productionRoot) {
                throw new InvalidArgumentException('Staging and production must use distinct domains and roots.');
            }
        }
    }

    public static function assertNoLegacyFiles(string $projectRoot): void
    {
        foreach (['.accelerator/deploy.env', '.accelerator/operations.env'] as $legacy) {
            if (is_file(rtrim($projectRoot, '/').'/'.$legacy)) {
                throw new RuntimeException("Legacy deployment file [{$legacy}] must be migrated first.");
            }
        }
    }

    private static function validateDeploymentKey(string $value): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $value) !== 1) {
            throw new InvalidArgumentException('deployment_key must be a lowercase slug between 2 and 63 characters.');
        }
    }

    private static function validateDomainRoot(string $domain, string $root): void
    {
        if (preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9.-]+(?<!-)$/i', $domain) !== 1 || $root !== "/var/www/{$domain}") {
            throw new InvalidArgumentException('Each deployment root must be exactly /var/www/{domain}.');
        }
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key, string $default = ''): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : $default;
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $key, int $default): int
    {
        return is_int($data[$key] ?? null) ? $data[$key] : $default;
    }

    /** @param array<string, mixed> $data */
    private static function bool(array $data, string $key, bool $default = false): bool
    {
        return is_bool($data[$key] ?? null) ? $data[$key] : $default;
    }
}
