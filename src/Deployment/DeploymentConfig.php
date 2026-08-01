<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final readonly class DeploymentConfig
{
    /**
     * @param  array<string, mixed>  $document
     */
    private function __construct(
        public string $projectRoot,
        public string $stage,
        public string $project,
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
        public string $httpRuntime,
        public string $fpmSocket,
        public string $fpmService,
        public string $octaneServer,
        public int $octanePort,
        public int $octaneWorkers,
        public int $octaneTaskWorkers,
        public bool $horizonEnabled,
        public bool $queueWorkerEnabled,
        public string $queueWorkerConnection,
        public string $queueWorkerQueue,
        public int $queueWorkerProcesses,
        public bool $reverbEnabled,
        public int $reverbPort,
        public bool $schedulerEnabled,
        public bool $nightwatchEnabled,
        public int $nightwatchPort,
        public string $healthPath,
        public array $document,
    ) {}

    /** @throws JsonException */
    public static function load(string $projectRoot, ?string $requestedStage = null): self
    {
        $projectRoot = rtrim($projectRoot, '/');
        self::assertNoLegacyFiles($projectRoot);
        $path = $projectRoot.'/.accelerator/deploy.json';
        $contents = @file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException('Deployment is not configured. Run: php artisan accelerator:configure deployment');
        }

        $document = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($document) || ($document['schema'] ?? null) !== 1) {
            throw new RuntimeException('deploy.json must use Accelerator deployment schema 1.');
        }

        self::validateTopologyDocument($document);

        $stage = $requestedStage ?: self::string($document, 'default_stage');
        $stages = $document['stages'] ?? null;
        $stageConfig = is_array($stages) ? ($stages[$stage] ?? null) : null;

        if (! in_array($stage, ['staging', 'production'], true) || ! is_array($stageConfig) || ($stageConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException("Deployment stage [{$stage}] is missing or disabled.");
        }

        $project = self::string($document, 'project');
        $repository = self::string($document, 'repository');
        $branch = self::string($document, 'branch');
        $phpVersion = self::string($document, 'php_version', '8.5');
        $phpBinary = self::string($document, 'php_binary');
        $phpBinary = $phpBinary !== '' ? $phpBinary : "php{$phpVersion}";
        $packageManager = self::string($document, 'package_manager', 'pnpm');
        $domain = self::string($stageConfig, 'domain');
        $deployRoot = rtrim(self::string($stageConfig, 'root', "/var/www/{$domain}"), '/');
        $httpRuntime = self::string($stageConfig, 'http_runtime', 'octane');
        $horizonEnabled = self::bool($stageConfig, 'horizon');
        $queueWorkerEnabled = self::bool($stageConfig, 'queue_worker', ! $horizonEnabled);

        self::validateInstallerTargets(
            project: $project,
            sshHost: self::string($stageConfig, 'ssh_host'),
            repository: $repository,
            branch: $branch,
            productionDomain: $domain,
            productionRoot: $deployRoot,
        );

        if (! in_array($packageManager, ['pnpm', 'npm'], true)) {
            throw new InvalidArgumentException('package_manager must be pnpm or npm.');
        }

        if (! in_array($httpRuntime, ['fpm', 'octane'], true)) {
            throw new InvalidArgumentException('http_runtime must be fpm or octane.');
        }

        if ($horizonEnabled && $queueWorkerEnabled) {
            throw new InvalidArgumentException('Horizon and the plain queue worker are mutually exclusive.');
        }

        return new self(
            projectRoot: $projectRoot,
            stage: $stage,
            project: $project,
            sshHost: self::string($stageConfig, 'ssh_host'),
            repository: $repository,
            branch: $branch,
            keepReleases: self::integer($document, 'keep_releases', 5),
            phpVersion: $phpVersion,
            phpBinary: $phpBinary,
            packageManager: $packageManager,
            runUser: self::string($document, 'run_user', 'www-data'),
            sslEmail: self::string($document, 'ssl_email'),
            domain: $domain,
            deployRoot: $deployRoot,
            group: self::string($stageConfig, 'service_group', self::defaultServiceGroup($project, $stage)),
            dnsDirect: self::bool($stageConfig, 'dns_direct', true),
            httpRuntime: $httpRuntime,
            fpmSocket: self::string($stageConfig, 'fpm_socket', "/run/php/php{$phpVersion}-fpm.sock"),
            fpmService: self::string($stageConfig, 'fpm_service', "php{$phpVersion}-fpm.service"),
            octaneServer: self::string($stageConfig, 'octane_server', 'swoole'),
            octanePort: self::port($stageConfig, 'octane_port', $stage === 'production' ? 8000 : 8100),
            octaneWorkers: self::integer($stageConfig, 'octane_workers', 4),
            octaneTaskWorkers: self::integer($stageConfig, 'octane_task_workers', 2),
            horizonEnabled: $horizonEnabled,
            queueWorkerEnabled: $queueWorkerEnabled,
            queueWorkerConnection: self::string($stageConfig, 'queue_connection', 'database'),
            queueWorkerQueue: self::string($stageConfig, 'queue', 'default'),
            queueWorkerProcesses: self::integer($stageConfig, 'queue_processes', 1),
            reverbEnabled: self::bool($stageConfig, 'reverb'),
            reverbPort: self::port($stageConfig, 'reverb_port', $stage === 'production' ? 8080 : 8180),
            schedulerEnabled: self::bool($stageConfig, 'scheduler', true),
            nightwatchEnabled: self::bool($stageConfig, 'nightwatch'),
            nightwatchPort: self::port($stageConfig, 'nightwatch_port', $stage === 'production' ? 2407 : 2507),
            healthPath: self::string($stageConfig, 'health_path', '/up'),
            document: $document,
        );
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

    public function hasSupervisorPrograms(): bool
    {
        return $this->httpRuntime === 'octane' || $this->horizonEnabled || $this->queueWorkerEnabled
            || $this->reverbEnabled || $this->schedulerEnabled || $this->nightwatchEnabled;
    }

    /** @return array<string, int> */
    public function listeningServices(): array
    {
        $services = [];

        if ($this->httpRuntime === 'octane') {
            $services['octane'] = $this->octanePort;
        }

        if ($this->reverbEnabled) {
            $services['reverb'] = $this->reverbPort;
        }

        if ($this->nightwatchEnabled) {
            $services['nightwatch'] = $this->nightwatchPort;
        }

        return $services;
    }

    public function ownerToken(string $kind): string
    {
        return implode(' ', [
            'WireNinja-Accelerator',
            "kind={$kind}",
            "project={$this->project}",
            "stage={$this->stage}",
            "domain={$this->domain}",
            "root={$this->deployRoot}",
            "group={$this->group}",
        ]);
    }

    public static function defaultServiceGroup(string $project, string $stage): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', trim("{$project}_{$stage}", '_')));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function validateTopologyDocument(array $document): void
    {
        $project = self::string($document, 'project');
        $stages = $document['stages'] ?? null;

        if (! is_array($stages)) {
            throw new InvalidArgumentException('Deployment stages must be an object.');
        }

        $enabledStages = [];
        $domains = [];
        $roots = [];
        $groups = [];
        $portsByHost = [];

        foreach (['staging', 'production'] as $stage) {
            $stageConfig = $stages[$stage] ?? null;

            if (! is_array($stageConfig) || ($stageConfig['enabled'] ?? false) !== true) {
                continue;
            }

            $enabledStages[] = $stage;
            $host = self::string($stageConfig, 'ssh_host');
            $domain = self::string($stageConfig, 'domain');
            $root = rtrim(self::string($stageConfig, 'root', "/var/www/{$domain}"), '/');
            $group = self::string($stageConfig, 'service_group', self::defaultServiceGroup($project, $stage));
            $runtime = self::string($stageConfig, 'http_runtime', 'octane');
            $horizon = self::bool($stageConfig, 'horizon');
            $queueWorker = self::bool($stageConfig, 'queue_worker', ! $horizon);

            self::validateDomainRoot($domain, $root);

            if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $host) !== 1) {
                throw new InvalidArgumentException("SSH host for {$stage} must be an alias from ~/.ssh/config.");
            }

            if (! in_array($runtime, ['fpm', 'octane'], true)) {
                throw new InvalidArgumentException("http_runtime for {$stage} must be fpm or octane.");
            }

            if ($horizon && $queueWorker) {
                throw new InvalidArgumentException("Horizon and the plain queue worker are mutually exclusive for {$stage}.");
            }

            if (preg_match('/^[a-z0-9][a-z0-9_]*$/', $group) !== 1) {
                throw new InvalidArgumentException("service_group [{$group}] for {$stage} must contain only lowercase letters, numbers, and underscores.");
            }

            if (isset($domains[$domain])) {
                throw new InvalidArgumentException("Stages [{$domains[$domain]}] and [{$stage}] must use distinct domains.");
            }

            if (isset($roots[$root])) {
                throw new InvalidArgumentException("Stages [{$roots[$root]}] and [{$stage}] must use distinct deployment roots.");
            }

            if (isset($groups[$group])) {
                throw new InvalidArgumentException("Stages [{$groups[$group]}] and [{$stage}] must use distinct service_group values.");
            }

            $domains[$domain] = $stage;
            $roots[$root] = $stage;
            $groups[$group] = $stage;

            $services = [];

            if ($runtime === 'octane') {
                $services['octane'] = self::port($stageConfig, 'octane_port', $stage === 'production' ? 8000 : 8100);
            }

            if (self::bool($stageConfig, 'reverb')) {
                $services['reverb'] = self::port($stageConfig, 'reverb_port', $stage === 'production' ? 8080 : 8180);
            }

            if (self::bool($stageConfig, 'nightwatch')) {
                $services['nightwatch'] = self::port($stageConfig, 'nightwatch_port', $stage === 'production' ? 2407 : 2507);
            }

            foreach ($services as $service => $port) {
                if (isset($portsByHost[$host][$port])) {
                    $owner = $portsByHost[$host][$port];

                    throw new InvalidArgumentException("Port {$port} on SSH host [{$host}] conflicts between {$owner} and {$stage}.{$service}.");
                }

                $portsByHost[$host][$port] = "{$stage}.{$service}";
            }
        }

        if ($enabledStages === []) {
            throw new InvalidArgumentException('At least one deployment stage must be enabled.');
        }

        $defaultStage = self::string($document, 'default_stage');

        if (! in_array($defaultStage, $enabledStages, true)) {
            throw new InvalidArgumentException('default_stage must identify an enabled deployment stage.');
        }
    }

    public static function validateInstallerTargets(
        string $project,
        string $sshHost,
        string $repository,
        string $branch,
        string $productionDomain,
        string $productionRoot,
        ?string $stagingDomain = null,
        ?string $stagingRoot = null,
    ): void {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $project) !== 1) {
            throw new InvalidArgumentException('Deployment project key is invalid.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $sshHost) !== 1) {
            throw new InvalidArgumentException('SSH host must be an alias from ~/.ssh/config.');
        }

        if ($repository === '' || preg_match('/\s/', $repository) === 1 || $branch === '' || preg_match('/\s/', $branch) === 1) {
            throw new InvalidArgumentException('Repository and branch are required and cannot contain whitespace.');
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
        $legacy = array_filter([
            is_file($projectRoot.'/.accelerator/deploy.env') ? '.accelerator/deploy.env' : null,
        ]);

        if ($legacy !== []) {
            throw new RuntimeException('Legacy deployment files detected: '.implode(', ', $legacy).'. Remove them after migrating to deploy.json.');
        }
    }

    /** @param array<string, mixed> $values */
    private static function string(array $values, string $key, string $default = ''): string
    {
        return is_string($values[$key] ?? null) ? trim($values[$key]) : $default;
    }

    /** @param array<string, mixed> $values */
    private static function bool(array $values, string $key, bool $default = false): bool
    {
        return is_bool($values[$key] ?? null) ? $values[$key] : $default;
    }

    /** @param array<string, mixed> $values */
    private static function integer(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? $default;

        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("{$key} must be a positive integer.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function port(array $values, string $key, int $default): int
    {
        $port = self::integer($values, $key, $default);

        if ($port < 1024 || $port > 65535) {
            throw new InvalidArgumentException("{$key} must be between 1024 and 65535.");
        }

        return $port;
    }

    private static function validateDomainRoot(string $domain, string $root): void
    {
        if (preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9.-]+(?<!-)$/i', $domain) !== 1) {
            throw new InvalidArgumentException("Invalid deployment domain [{$domain}].");
        }

        if ($root !== "/var/www/{$domain}") {
            throw new InvalidArgumentException("Deployment root must be /var/www/{$domain}.");
        }
    }
}
