<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use InvalidArgumentException;
use RuntimeException;

final readonly class DeploymentConfig
{
    /** @var list<string> */
    private const STAGES = ['staging', 'production'];

    public function __construct(
        public string $projectRoot,
        public string $stage,
        public string $project,
        public string $sshHost,
        public string $repository,
        public string $branch,
        public int $keepReleases,
        public string $phpVersion,
        public string $phpBinary,
        public string $bunBinary,
        public string $runUser,
        public string $sslEmail,
        public bool $initialAdminEnabled,
        public string $adminName,
        public string $adminUsername,
        public string $adminEmail,
        public string $adminPasswordHash,
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
    ) {}

    public static function load(string $projectRoot, ?string $requestedStage = null): self
    {
        $projectRoot = rtrim($projectRoot, '/');
        $values = self::parse($projectRoot.'/.env.envoy');
        self::rejectUnknownKeys($values);
        self::validateEnabledStageIsolation($values);

        $stage = $requestedStage ?: self::required($values, 'OPS_DEPLOY_DEFAULT_STAGE');

        if (! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException("Unsupported deploy stage [{$stage}]. Expected staging or production.");
        }

        $stageKey = strtoupper($stage);

        if (! self::boolean($values, "OPS_DEPLOY_{$stageKey}_ENABLED")) {
            throw new RuntimeException("Deploy stage [{$stage}] is disabled in .env.envoy.");
        }

        $project = self::required($values, 'OPS_DEPLOY_PROJECT');
        $sshHost = self::required($values, 'OPS_DEPLOY_SSH_HOST');
        $repository = self::required($values, 'OPS_DEPLOY_REPO');
        $branch = self::required($values, 'OPS_DEPLOY_BRANCH');
        $keepReleases = self::integer($values, 'OPS_DEPLOY_KEEP_RELEASES', 2, 50);
        $phpVersion = self::required($values, 'OPS_DEPLOY_PHP_VERSION');
        $phpBinary = self::value($values, 'OPS_DEPLOY_PHP_BIN', "php{$phpVersion}");
        $bunBinary = self::value($values, 'OPS_DEPLOY_BUN_BIN', 'bun');
        $runUser = self::value($values, 'OPS_DEPLOY_RUN_USER', 'www-data');
        $sslEmail = self::required($values, 'OPS_DEPLOY_SSL_EMAIL');
        $initialAdminEnabled = self::boolean($values, 'OPS_DEPLOY_INITIAL_ADMIN_ENABLED');
        $adminName = self::value($values, 'OPS_DEPLOY_INITIAL_ADMIN_NAME');
        $adminUsername = self::value($values, 'OPS_DEPLOY_INITIAL_ADMIN_USERNAME');
        $adminEmail = self::value($values, 'OPS_DEPLOY_INITIAL_ADMIN_EMAIL');
        $adminPasswordHash = self::value($values, 'OPS_DEPLOY_INITIAL_ADMIN_PASSWORD_HASH');
        $domain = self::required($values, "OPS_DEPLOY_{$stageKey}_DOMAIN");
        $deployRoot = rtrim(self::required($values, "OPS_DEPLOY_{$stageKey}_ROOT"), '/');
        $group = self::required($values, "OPS_DEPLOY_{$stageKey}_GROUP");
        $dnsDirect = self::boolean($values, "OPS_DEPLOY_{$stageKey}_DNS_DIRECT");
        $httpRuntime = self::value($values, "OPS_DEPLOY_{$stageKey}_HTTP_RUNTIME", 'fpm');
        $fpmPool = self::value($values, "OPS_DEPLOY_{$stageKey}_FPM_POOL");
        $fpmSocket = self::value(
            $values,
            "OPS_DEPLOY_{$stageKey}_FPM_SOCKET",
            $fpmPool === '' ? "/run/php/php{$phpVersion}-fpm.sock" : "/run/php/php{$phpVersion}-fpm-{$fpmPool}.sock",
        );
        $fpmService = self::value($values, "OPS_DEPLOY_{$stageKey}_FPM_SERVICE", "php{$phpVersion}-fpm.service");
        $octaneServer = self::value($values, "OPS_DEPLOY_{$stageKey}_OCTANE_SERVER", 'swoole');
        $octanePort = self::integer($values, "OPS_DEPLOY_{$stageKey}_OCTANE_PORT", 1, 65535);
        $octaneWorkers = self::integer($values, "OPS_DEPLOY_{$stageKey}_OCTANE_WORKERS", 1, 512);
        $octaneTaskWorkers = self::integer($values, "OPS_DEPLOY_{$stageKey}_OCTANE_TASK_WORKERS", 0, 512);
        $horizonEnabled = self::boolean($values, "OPS_DEPLOY_{$stageKey}_HORIZON_ENABLED");
        $queueWorkerEnabled = self::boolean($values, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_ENABLED");
        $queueWorkerConnection = self::value($values, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_CONNECTION", 'database');
        $queueWorkerQueue = self::value($values, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_QUEUE", 'default');
        $queueWorkerProcesses = self::integer($values, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_PROCESSES", 1, 128);
        $reverbEnabled = self::boolean($values, "OPS_DEPLOY_{$stageKey}_REVERB_ENABLED");
        $reverbPort = self::integer($values, "OPS_DEPLOY_{$stageKey}_REVERB_PORT", 1, 65535);
        $schedulerEnabled = self::boolean($values, "OPS_DEPLOY_{$stageKey}_SCHEDULER_ENABLED");
        $nightwatchEnabled = self::boolean($values, "OPS_DEPLOY_{$stageKey}_NIGHTWATCH_ENABLED");
        $nightwatchPort = self::integer($values, "OPS_DEPLOY_{$stageKey}_NIGHTWATCH_PORT", 1, 65535);

        self::validateIdentifier($project, 'project');
        self::validateSshHost($sshHost);
        self::validateRepository($repository);
        self::validateBranch($branch);
        self::validateVersion($phpVersion);
        self::validateCommand($phpBinary, 'PHP binary');
        self::validateCommand($bunBinary, 'Bun binary');
        self::validateIdentifier($runUser, 'runtime user');
        self::validateEmail($sslEmail, 'SSL email');
        if ($initialAdminEnabled) {
            self::validateAdmin($adminName, $adminUsername, $adminEmail, $adminPasswordHash);
        }
        self::validateDomain($domain);
        self::validateDeployRoot($deployRoot);
        self::validateIdentifier($group, 'Supervisor group');
        self::validateRuntime($httpRuntime, $octaneServer);
        self::validateFpm($fpmPool, $fpmSocket, $fpmService);
        self::validateQueue($horizonEnabled, $queueWorkerEnabled, $queueWorkerConnection, $queueWorkerQueue);
        self::validatePorts($httpRuntime, $octanePort, $reverbEnabled, $reverbPort, $nightwatchEnabled, $nightwatchPort);

        return new self(
            projectRoot: $projectRoot,
            stage: $stage,
            project: $project,
            sshHost: $sshHost,
            repository: $repository,
            branch: $branch,
            keepReleases: $keepReleases,
            phpVersion: $phpVersion,
            phpBinary: $phpBinary,
            bunBinary: $bunBinary,
            runUser: $runUser,
            sslEmail: $sslEmail,
            initialAdminEnabled: $initialAdminEnabled,
            adminName: $adminName,
            adminUsername: $adminUsername,
            adminEmail: $adminEmail,
            adminPasswordHash: $adminPasswordHash,
            domain: $domain,
            deployRoot: $deployRoot,
            group: $group,
            dnsDirect: $dnsDirect,
            httpRuntime: $httpRuntime,
            fpmSocket: $fpmSocket,
            fpmService: $fpmService,
            octaneServer: $octaneServer,
            octanePort: $octanePort,
            octaneWorkers: $octaneWorkers,
            octaneTaskWorkers: $octaneTaskWorkers,
            horizonEnabled: $horizonEnabled,
            queueWorkerEnabled: $queueWorkerEnabled,
            queueWorkerConnection: $queueWorkerConnection,
            queueWorkerQueue: $queueWorkerQueue,
            queueWorkerProcesses: $queueWorkerProcesses,
            reverbEnabled: $reverbEnabled,
            reverbPort: $reverbPort,
            schedulerEnabled: $schedulerEnabled,
            nightwatchEnabled: $nightwatchEnabled,
            nightwatchPort: $nightwatchPort,
        );
    }

    public function runtimeEnvironmentFile(): string
    {
        return $this->projectRoot.'/.env.'.$this->stage;
    }

    public function sharedPath(): string
    {
        return $this->deployRoot.'/shared';
    }

    public function releasesPath(): string
    {
        return $this->deployRoot.'/releases';
    }

    public function archivePath(): string
    {
        return $this->deployRoot.'/archive';
    }

    public function currentPath(): string
    {
        return $this->deployRoot.'/current';
    }

    public function hasSupervisorPrograms(): bool
    {
        return $this->httpRuntime === 'octane'
            || $this->horizonEnabled
            || $this->queueWorkerEnabled
            || $this->reverbEnabled
            || $this->schedulerEnabled
            || $this->nightwatchEnabled;
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
        self::validateIdentifier($project, 'project');
        self::validateSshHost($sshHost);
        self::validateRepository($repository);
        self::validateBranch($branch);
        self::validateDomain($productionDomain);
        self::validateDeployRoot($productionRoot);

        if ($stagingDomain === null || $stagingRoot === null) {
            return;
        }

        self::validateDomain($stagingDomain);
        self::validateDeployRoot($stagingRoot);

        if ($stagingDomain === $productionDomain || $stagingRoot === $productionRoot) {
            throw new InvalidArgumentException('Staging and production must use distinct domains and deployment roots.');
        }
    }

    /**
     * @return array<string, string>
     */
    private static function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Missing .env.envoy. Re-run onboarding or create it from the v2 template.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if (! is_array($lines)) {
            throw new RuntimeException('Unable to read .env.envoy.');
        }

        $values = [];

        foreach ($lines as $number => $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                throw new RuntimeException(sprintf('Invalid .env.envoy line %d: expected KEY=VALUE.', $number + 1));
            }

            [$key, $value] = array_map(trim(...), explode('=', $line, 2));

            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                throw new RuntimeException(sprintf('Invalid .env.envoy key on line %d.', $number + 1));
            }

            if (array_key_exists($key, $values)) {
                throw new RuntimeException("Duplicate .env.envoy key [{$key}].");
            }

            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function rejectUnknownKeys(array $values): void
    {
        $allowed = [
            'OPS_DEPLOY_DEFAULT_STAGE',
            'OPS_DEPLOY_PROJECT',
            'OPS_DEPLOY_SSH_HOST',
            'OPS_DEPLOY_REPO',
            'OPS_DEPLOY_BRANCH',
            'OPS_DEPLOY_KEEP_RELEASES',
            'OPS_DEPLOY_PHP_VERSION',
            'OPS_DEPLOY_PHP_BIN',
            'OPS_DEPLOY_BUN_BIN',
            'OPS_DEPLOY_RUN_USER',
            'OPS_DEPLOY_SSL_EMAIL',
            'OPS_DEPLOY_INITIAL_ADMIN_ENABLED',
            'OPS_DEPLOY_INITIAL_ADMIN_NAME',
            'OPS_DEPLOY_INITIAL_ADMIN_USERNAME',
            'OPS_DEPLOY_INITIAL_ADMIN_EMAIL',
            'OPS_DEPLOY_INITIAL_ADMIN_PASSWORD_HASH',
        ];
        $stageSuffixes = [
            'ENABLED',
            'DOMAIN',
            'ROOT',
            'GROUP',
            'DNS_DIRECT',
            'HTTP_RUNTIME',
            'FPM_POOL',
            'FPM_SOCKET',
            'FPM_SERVICE',
            'OCTANE_SERVER',
            'OCTANE_PORT',
            'OCTANE_WORKERS',
            'OCTANE_TASK_WORKERS',
            'HORIZON_ENABLED',
            'QUEUE_WORKER_ENABLED',
            'QUEUE_WORKER_CONNECTION',
            'QUEUE_WORKER_QUEUE',
            'QUEUE_WORKER_PROCESSES',
            'REVERB_ENABLED',
            'REVERB_PORT',
            'SCHEDULER_ENABLED',
            'NIGHTWATCH_ENABLED',
            'NIGHTWATCH_PORT',
        ];

        foreach (self::STAGES as $stage) {
            foreach ($stageSuffixes as $suffix) {
                $allowed[] = 'OPS_DEPLOY_'.strtoupper($stage).'_'.$suffix;
            }
        }

        $unknown = array_values(array_diff(array_keys($values), $allowed));

        if ($unknown !== []) {
            throw new RuntimeException('Unknown or legacy .env.envoy keys: '.implode(', ', $unknown));
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function validateEnabledStageIsolation(array $values): void
    {
        $identities = [
            'domain' => [],
            'root' => [],
            'group' => [],
        ];
        $ports = [];

        foreach (self::STAGES as $stage) {
            $prefix = 'OPS_DEPLOY_'.strtoupper($stage).'_';

            if (! self::boolean($values, $prefix.'ENABLED')) {
                continue;
            }

            foreach (['DOMAIN' => 'domain', 'ROOT' => 'root', 'GROUP' => 'group'] as $suffix => $identity) {
                $value = rtrim(self::required($values, $prefix.$suffix), '/');

                if (isset($identities[$identity][$value])) {
                    throw new InvalidArgumentException("Enabled stages must use distinct {$identity} values.");
                }

                $identities[$identity][$value] = true;
            }

            $runtime = self::value($values, $prefix.'HTTP_RUNTIME', 'fpm');
            $stagePorts = [];

            if ($runtime === 'octane') {
                $stagePorts[] = self::integer($values, $prefix.'OCTANE_PORT', 1, 65535);
            }

            if (self::boolean($values, $prefix.'REVERB_ENABLED')) {
                $stagePorts[] = self::integer($values, $prefix.'REVERB_PORT', 1, 65535);
            }

            if (self::boolean($values, $prefix.'NIGHTWATCH_ENABLED')) {
                $stagePorts[] = self::integer($values, $prefix.'NIGHTWATCH_PORT', 1, 65535);
            }

            foreach ($stagePorts as $port) {
                if (isset($ports[$port])) {
                    throw new InvalidArgumentException("Enabled stages and services must use distinct local port [{$port}].");
                }

                $ports[$port] = true;
            }
        }
    }

    /** @param array<string, string> $values */
    private static function required(array $values, string $key): string
    {
        $value = self::value($values, $key);

        if ($value === '') {
            throw new RuntimeException("Missing required .env.envoy key [{$key}].");
        }

        return $value;
    }

    /** @param array<string, string> $values */
    private static function value(array $values, string $key, string $default = ''): string
    {
        return array_key_exists($key, $values) && $values[$key] !== '' ? $values[$key] : $default;
    }

    /** @param array<string, string> $values */
    private static function boolean(array $values, string $key): bool
    {
        return match (strtolower(self::required($values, $key))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new InvalidArgumentException("Invalid boolean value for [{$key}]."),
        };
    }

    /** @param array<string, string> $values */
    private static function integer(array $values, string $key, int $minimum, int $maximum): int
    {
        $value = self::required($values, $key);

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("Invalid integer value for [{$key}].");
        }

        $integer = (int) $value;

        if ($integer < $minimum || $integer > $maximum) {
            throw new InvalidArgumentException("[{$key}] must be between {$minimum} and {$maximum}.");
        }

        return $integer;
    }

    private static function validateIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $value) !== 1 || str_starts_with($value, '-')) {
            throw new InvalidArgumentException("Invalid {$label} [{$value}].");
        }
    }

    private static function validateSshHost(string $value): void
    {
        if (preg_match('/^[A-Za-z0-9_.@:-]+$/', $value) !== 1 || str_starts_with($value, '-')) {
            throw new InvalidArgumentException("Invalid SSH host [{$value}].");
        }
    }

    private static function validateRepository(string $value): void
    {
        if (preg_match('/[\s\x00-\x1F\x7F]/', $value) === 1 || str_starts_with($value, '-')) {
            throw new InvalidArgumentException('The deploy repository URL is invalid.');
        }
    }

    private static function validateBranch(string $value): void
    {
        if (preg_match('/^[A-Za-z0-9._\/-]+$/', $value) !== 1 || str_starts_with($value, '-') || str_contains($value, '..')) {
            throw new InvalidArgumentException("Invalid deploy branch [{$value}].");
        }
    }

    private static function validateVersion(string $value): void
    {
        if (preg_match('/^\d+\.\d+$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid PHP version [{$value}].");
        }
    }

    private static function validateCommand(string $value, string $label): void
    {
        if (preg_match('#^(?:[A-Za-z0-9_.-]+|/[A-Za-z0-9_./-]+)$#', $value) !== 1 || str_contains($value, '..')) {
            throw new InvalidArgumentException("Invalid {$label} [{$value}].");
        }
    }

    private static function validateEmail(string $value, string $label): void
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid {$label} [{$value}].");
        }
    }

    private static function validateAdmin(string $name, string $username, string $email, string $passwordHash): void
    {
        if (trim($name) === '' || preg_match('/[\x00-\x1F\x7F]/', $name) === 1 || strlen($name) > 100) {
            throw new InvalidArgumentException('Invalid initial administrator name.');
        }

        if (preg_match('/^[a-z0-9._-]+$/', $username) !== 1) {
            throw new InvalidArgumentException('Invalid initial administrator username.');
        }

        self::validateEmail($email, 'initial administrator email');

        if (! password_get_info($passwordHash)['algo']) {
            throw new InvalidArgumentException('Invalid initial administrator password hash.');
        }
    }

    private static function validateDomain(string $value): void
    {
        if (
            preg_match('/^(?=.{1,253}$)(?!-)[A-Za-z0-9.-]+(?<!-)$/', $value) !== 1
            || ! str_contains($value, '.')
            || str_starts_with($value, '.')
            || str_contains($value, '..')
        ) {
            throw new InvalidArgumentException("Invalid deploy domain [{$value}].");
        }
    }

    private static function validateDeployRoot(string $value): void
    {
        if (
            in_array($value, ['/', '/home', '/srv', '/var', '/var/www'], true)
            || preg_match('#^/[A-Za-z0-9_./-]+$#', $value) !== 1
            || str_contains($value, '..')
            || str_contains($value, '//')
        ) {
            throw new InvalidArgumentException("Unsafe deploy root [{$value}].");
        }
    }

    private static function validateRuntime(string $httpRuntime, string $octaneServer): void
    {
        if (! in_array($httpRuntime, ['fpm', 'octane'], true)) {
            throw new InvalidArgumentException("Invalid HTTP runtime [{$httpRuntime}].");
        }

        if (! in_array($octaneServer, ['swoole', 'roadrunner', 'frankenphp'], true)) {
            throw new InvalidArgumentException("Invalid Octane server [{$octaneServer}].");
        }
    }

    private static function validateFpm(string $pool, string $socket, string $service): void
    {
        if ($pool !== '') {
            self::validateIdentifier($pool, 'FPM pool');
        }

        if (preg_match('#^/[A-Za-z0-9_./-]+\.sock$#', $socket) !== 1 || str_contains($socket, '..')) {
            throw new InvalidArgumentException("Invalid FPM socket [{$socket}].");
        }

        if (preg_match('/^[A-Za-z0-9_.@-]+\.service$/', $service) !== 1) {
            throw new InvalidArgumentException("Invalid FPM service [{$service}].");
        }
    }

    private static function validateQueue(bool $horizon, bool $worker, string $connection, string $queue): void
    {
        if ($horizon && $worker) {
            throw new InvalidArgumentException('Horizon and the plain queue worker cannot both be enabled.');
        }

        if (preg_match('/^[A-Za-z0-9_.-]+$/', $connection) !== 1 || preg_match('/^[A-Za-z0-9_,.-]+$/', $queue) !== 1) {
            throw new InvalidArgumentException('Invalid queue worker connection or queue list.');
        }
    }

    private static function validatePorts(
        string $httpRuntime,
        int $octanePort,
        bool $reverbEnabled,
        int $reverbPort,
        bool $nightwatchEnabled,
        int $nightwatchPort,
    ): void {
        $ports = [];

        if ($httpRuntime === 'octane') {
            $ports[] = $octanePort;
        }

        if ($reverbEnabled) {
            $ports[] = $reverbPort;
        }

        if ($nightwatchEnabled) {
            $ports[] = $nightwatchPort;
        }

        if (count($ports) !== count(array_unique($ports))) {
            throw new InvalidArgumentException('Enabled deployment services must use distinct ports.');
        }
    }
}
