{{--
  Accelerator Envoy release deployer
  ----------------------------------

  This file is intentionally shell-first. It does not bootstrap Laravel.
  Deployment config is read from `.env.envoy` in the project root using a tiny
  local PHP parser.

  Stories:
    init         First-time deploy. Layout + first release. SKIPS db-backup,
                 maintenance, prune (would crash on empty state).
    deploy       Continuous full deploy with asset rebuild. INCLUDES db-backup,
                 maintenance window, health-check, prune.
    deploy-slim  Continuous deploy minus build-release (hot patch backend only).
    deploy-fresh-seed
                 DESTRUCTIVE redeploy that runs migrate:fresh --seed with dev
                 dependencies available, then prunes dev packages before switch.
    rollback    Atomic switch back to a verified previous release. NO maintenance
                 window — emergency speed prioritised.
    releases     Print release history + current pointer + prune target.
    status, restart, logs
                 Operate only on programs configured for the selected stage.
--}}

@setup
    $root = getcwd();
    $envoyFile = $root.'/.env.envoy';

    if (! is_file($envoyFile)) {
        throw new RuntimeException('Missing .env.envoy. Create it from OPS_DEPLOY_* values before running Envoy.');
    }

    $parseEnvFile = function (string $path): array {
        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    };

    $value = function (array $env, string $key, ?string $default = null): string {
        return array_key_exists($key, $env) && $env[$key] !== '' ? $env[$key] : (string) $default;
    };

    $truthy = fn (string $value): bool => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);

    $envoy = $parseEnvFile($envoyFile);
    $stage = isset($stage) ? $stage : (isset($env) ? $env : $value($envoy, 'OPS_DEPLOY_DEFAULT_STAGE', 'test'));
    $stageKey = strtoupper($stage);

    if (! in_array($stage, ['test', 'prod'], true)) {
        throw new InvalidArgumentException('Unsupported deploy stage ['.$stage.']. Expected [test] or [prod].');
    }

    $enabled = $truthy($value($envoy, "OPS_DEPLOY_{$stageKey}_ENABLED", 'true'));

    if (! $enabled) {
        throw new RuntimeException('Deploy stage ['.$stage.'] is disabled in .env.envoy.');
    }

    $project = $value($envoy, 'OPS_DEPLOY_PROJECT', 'laravel');
    $domain = $value($envoy, "OPS_DEPLOY_{$stageKey}_DOMAIN");
    $deployRoot = rtrim($value($envoy, "OPS_DEPLOY_{$stageKey}_ROOT"), '/');
    $repo = $value($envoy, "OPS_DEPLOY_{$stageKey}_REPO", $value($envoy, 'OPS_DEPLOY_REPO'));
    $branch = $value($envoy, "OPS_DEPLOY_{$stageKey}_BRANCH", $value($envoy, 'OPS_DEPLOY_BRANCH', 'main'));
    $group = $value($envoy, "OPS_DEPLOY_{$stageKey}_GROUP");
    $phpBin = $value($envoy, "OPS_DEPLOY_{$stageKey}_PHP_BIN", $value($envoy, 'OPS_DEPLOY_PHP_BIN', 'php'));
    $npmBin = $value($envoy, "OPS_DEPLOY_{$stageKey}_NPM_BIN", $value($envoy, 'OPS_DEPLOY_NPM_BIN', ''));

    // Auto-detect package manager when not explicitly set.
    if ($npmBin === '') {
        $detect = trim((string) shell_exec('which pnpm 2>/dev/null || which bun 2>/dev/null || which npm 2>/dev/null'));
        $npmBin = $detect !== '' ? $detect : 'npm';
    }
    $runUser = $value($envoy, "OPS_DEPLOY_{$stageKey}_RUN_USER", $value($envoy, 'OPS_DEPLOY_RUN_USER', 'www-data'));
    $sshHost = $value($envoy, "OPS_DEPLOY_{$stageKey}_SSH_HOST", $value($envoy, 'OPS_DEPLOY_SSH_HOST', 'onidel'));
    $httpRuntime = $value($envoy, "OPS_DEPLOY_{$stageKey}_HTTP_RUNTIME", 'fpm');
    $runtime = $value($envoy, "OPS_DEPLOY_{$stageKey}_RUNTIME", 'swoole');
    $fpmSocket = $value($envoy, "OPS_DEPLOY_{$stageKey}_FPM_SOCKET", '/run/php/php8.5-fpm.sock');
    $octanePort = $value($envoy, "OPS_DEPLOY_{$stageKey}_OCTANE_PORT");
    $octaneWorkers = $value($envoy, "OPS_DEPLOY_{$stageKey}_OCTANE_WORKERS", '1');
    $octaneTaskWorkers = $value($envoy, "OPS_DEPLOY_{$stageKey}_OCTANE_TASK_WORKERS", '0');
    $horizonEnabled = $truthy($value($envoy, "OPS_DEPLOY_{$stageKey}_HORIZON_ENABLED", 'false'));
    $queueWorkerEnabled = $truthy($value($envoy, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_ENABLED", 'false'));
    $queueWorkerConnection = $value($envoy, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_CONNECTION", 'redis');
    $queueWorkerQueue = $value($envoy, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_QUEUE", 'default');
    $queueWorkerProcesses = $value($envoy, "OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_PROCESSES", '1');
    $reverbEnabled = $truthy($value($envoy, "OPS_DEPLOY_{$stageKey}_REVERB_ENABLED", 'false'));
    $reverbPort = $value($envoy, "OPS_DEPLOY_{$stageKey}_REVERB_PORT");
    $schedulerEnabled = $truthy($value($envoy, "OPS_DEPLOY_{$stageKey}_SCHEDULER_ENABLED", 'false'));
    $nightwatchEnabled = $truthy($value($envoy, "OPS_DEPLOY_{$stageKey}_NIGHTWATCH_ENABLED", 'false'));
    $nightwatchPort = $value($envoy, "OPS_DEPLOY_{$stageKey}_NIGHTWATCH_PORT");
    $keepReleases = (int) $value($envoy, 'OPS_DEPLOY_KEEP_RELEASES', '5');
    $service = isset($service) ? $service : 'all';
    $seedEnvFile = $root.'/'.($stage === 'prod' ? '.env.production' : '.env.staging');
    $freshSeedConfirmationPhrase = 'aku mengkonfirmasi remigrate fresh seed';
    $freshSeedConfirmation = isset($iUnderstandThisWillDropAndReseedDatabase)
        ? (string) $iUnderstandThisWillDropAndReseedDatabase
        : (isset($i_understand_this_will_drop_and_reseed_database) ? (string) $i_understand_this_will_drop_and_reseed_database : '');
    $requestedTask = isset($__task) ? (string) $__task : '';
    $freshSeedProtectedTasks = ['deploy-fresh-seed', 'assert-fresh-seed-confirmed', 'prepare-laravel-fresh-seed'];

    if (in_array($requestedTask, $freshSeedProtectedTasks, true) && $freshSeedConfirmation !== $freshSeedConfirmationPhrase) {
        throw new RuntimeException(
            'Refusing destructive fresh-seed deploy. Re-run with --i-understand-this-will-drop-and-reseed-database="'.
            $freshSeedConfirmationPhrase.
            '".'
        );
    }

    foreach (['domain' => $domain, 'root' => $deployRoot, 'repo' => $repo, 'group' => $group] as $name => $required) {
        if ($required === '') {
            throw new RuntimeException("Missing OPS deploy {$name} for stage [{$stage}] in .env.envoy.");
        }
    }

    if (! in_array($httpRuntime, ['fpm', 'octane'], true)) {
        throw new RuntimeException("Invalid HTTP runtime [{$httpRuntime}] for stage [{$stage}]. Expected [fpm] or [octane].");
    }

    if ($httpRuntime === 'fpm' && $fpmSocket === '') {
        throw new RuntimeException("Missing OPS_DEPLOY_{$stageKey}_FPM_SOCKET for FPM stage [{$stage}].");
    }

    if ($httpRuntime === 'octane') {
        if ($octanePort === '') {
            throw new RuntimeException("Missing OPS_DEPLOY_{$stageKey}_OCTANE_PORT for Octane stage [{$stage}].");
        }

        if (! in_array($runtime, ['swoole', 'roadrunner', 'frankenphp'], true)) {
            throw new RuntimeException("Invalid Octane runtime [{$runtime}] for stage [{$stage}].");
        }

        if (! ctype_digit($octaneWorkers) || (int) $octaneWorkers < 1) {
            throw new RuntimeException("OPS_DEPLOY_{$stageKey}_OCTANE_WORKERS must be an integer greater than zero.");
        }

        if (! ctype_digit($octaneTaskWorkers)) {
            throw new RuntimeException("OPS_DEPLOY_{$stageKey}_OCTANE_TASK_WORKERS must be an integer greater than or equal to zero.");
        }
    }

    if ($horizonEnabled && $queueWorkerEnabled) {
        throw new RuntimeException("OPS_DEPLOY_{$stageKey}_HORIZON_ENABLED and OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_ENABLED cannot both be true.");
    }

    if ($queueWorkerEnabled) {
        if (! preg_match('/^[A-Za-z0-9_.-]+$/', $queueWorkerConnection) || ! preg_match('/^[A-Za-z0-9_,.-]+$/', $queueWorkerQueue)) {
            throw new RuntimeException("Invalid queue worker connection or queue for stage [{$stage}].");
        }

        if (! ctype_digit($queueWorkerProcesses) || (int) $queueWorkerProcesses < 1) {
            throw new RuntimeException("OPS_DEPLOY_{$stageKey}_QUEUE_WORKER_PROCESSES must be an integer greater than zero.");
        }
    }

    if ($reverbEnabled && (! ctype_digit($reverbPort) || (int) $reverbPort < 1)) {
        throw new RuntimeException("OPS_DEPLOY_{$stageKey}_REVERB_PORT must be an integer greater than zero when Reverb is enabled.");
    }

    if ($nightwatchEnabled && (! ctype_digit($nightwatchPort) || (int) $nightwatchPort < 1)) {
        throw new RuntimeException("OPS_DEPLOY_{$stageKey}_NIGHTWATCH_PORT must be an integer greater than zero when Nightwatch is enabled.");
    }

    $sharedPath = $deployRoot.'/shared';
    $releasesPath = $deployRoot.'/releases';
    $archivePath = $deployRoot.'/archive';
    $currentPath = $deployRoot.'/current';
    $releaseSha = trim((string) shell_exec('git ls-remote '.escapeshellarg($repo).' '.escapeshellarg($branch).' | awk \'{print $1}\''));

    if ($releaseSha === '') {
        throw new RuntimeException("Unable to resolve remote SHA for [{$repo}] [{$branch}].");
    }

    $releaseShortSha = substr($releaseSha, 0, 7);
    $releaseId = date('Y-m-d_H-i-s').'_'.$releaseShortSha;
    $releasePath = $releasesPath.'/'.$releaseId;
    $nextSymlink = $deployRoot.'/current.next';
    $rollbackSymlink = $deployRoot.'/current.rollback';

    /*
     * Maintenance secret — random per deploy, surfaced once in Envoy output so
     * the operator can bypass the down() page during the migrate window.
     */
    $maintenanceSecret = bin2hex(random_bytes(16));

    /*
     * Bootstrap stub paths — used by `bootstrap-nginx` / `bootstrap-supervisor`
     * to render initial /etc/nginx + /etc/supervisor config.
     *
     * The stubs ship with the accelerator package; resolve from vendor first,
     * fall back to the in-tree path when running from the package itself.
     */
    $nginxStub = $root.'/vendor/wireninja/accelerator/stubs/vps/nginx-vhost-http.conf.stub';
    if (! is_file($nginxStub)) {
        $nginxStub = $root.'/packages/accelerator/stubs/vps/nginx-vhost-http.conf.stub';
    }
    $nginxSslStub = $root.'/vendor/wireninja/accelerator/stubs/vps/nginx-vhost-ssl.conf.stub';
    if (! is_file($nginxSslStub)) {
        $nginxSslStub = $root.'/packages/accelerator/stubs/vps/nginx-vhost-ssl.conf.stub';
    }
    $supervisorStub = $root.'/vendor/wireninja/accelerator/stubs/vps/supervisor.conf.stub';
    if (! is_file($supervisorStub)) {
        $supervisorStub = $root.'/packages/accelerator/stubs/vps/supervisor.conf.stub';
    }

    $sslEmail = $value($envoy, 'OPS_DEPLOY_SSL_EMAIL', 'admin@'.$domain);

    /*
     * The public `octane:start` command converts `--task-workers=0` back to
     * Octane's `auto` default. Call the registered Swoole command directly so
     * zero genuinely disables optional task workers.
     */
    $octaneServerCommand = $runtime === 'swoole'
        ? 'octane:swoole'
        : "octane:start --server={$runtime}";
    $octaneTaskWorkersOption = $runtime === 'swoole'
        ? "--task-workers={$octaneTaskWorkers}"
        : '';

    /*
     * Render bootstrap stubs locally so we can scp the rendered output to the
     * VPS. Pure str_replace — keeps stubs framework-free and reviewable.
     */
    $replaceVars = function (string $content, array $vars): string {
        foreach ($vars as $key => $value) {
            $content = str_replace('{' . '{ ' . $key . ' }' . '}', (string) $value, $content);
            $content = str_replace('%%'.$key.'%%', (string) $value, $content);
        }

        return $content;
    };

    $renderStub = function (string $stubPath, array $vars) use ($replaceVars): string {
        if (! is_file($stubPath)) {
            throw new RuntimeException("Bootstrap stub not found: {$stubPath}");
        }

        return $replaceVars(file_get_contents($stubPath), $vars);
    };

    $reverbLocation = $reverbEnabled ? $replaceVars(<<<'NGINX'
    # Reverb websocket
    location ~ ^/(app|apps|pusher)/ {
        proxy_pass http://127.0.0.1:%%reverb_port%%;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
    }

NGINX, ['reverb_port' => $reverbPort]) : '';

    if ($httpRuntime === 'octane') {
        $dynamicFallback = '@octane';
        $applicationLocations = $replaceVars(<<<'NGINX'
    # Static assets - nginx serves directly, fallback to Octane
    location = /index.php { try_files /not_exists @octane; }
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot|map|txt)$ {
        try_files $uri @octane;
        expires 365d;
        add_header Cache-Control "public, immutable";
    }

    location / { try_files $uri @octane; }

    location @octane {
        set $suffix "";
        if ($uri = /index.php) { set $suffix ?$query_string; }
        proxy_pass http://127.0.0.1:%%octane_port%%$suffix;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-Host $host;
        proxy_buffering off;
        proxy_read_timeout 60s;
    }
NGINX, ['octane_port' => $octanePort]);
    } else {
        $dynamicFallback = '/index.php?$query_string';
        $applicationLocations = $replaceVars(<<<'NGINX'
    # Static assets - nginx serves directly, fallback to PHP-FPM
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot|map|txt)$ {
        try_files $uri /index.php?$query_string;
        expires 365d;
        add_header Cache-Control "public, immutable";
    }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:%%fpm_socket%%;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
NGINX, ['fpm_socket' => $fpmSocket]);
    }

    $serviceWorkerLocation = $replaceVars(<<<'NGINX'
    # SW served from /build/ but must be at root scope for PWA
    location = /build/sw.js {
        rewrite ^ /sw.js break;
        expires 0;
        add_header Cache-Control "no-cache";
        default_type application/javascript;
        try_files $uri %%dynamic_fallback%%;
    }

NGINX, ['dynamic_fallback' => $dynamicFallback]);

    $supervisorProgramNames = [];
    $supervisorDefinitions = [];
    $addSupervisorProgram = function (string $program, string $definition) use (&$supervisorProgramNames, &$supervisorDefinitions): void {
        $supervisorProgramNames[] = $program;
        $supervisorDefinitions[] = $definition;
    };

    if ($httpRuntime === 'octane') {
        $addSupervisorProgram("{$group}_octane", $replaceVars(<<<'CONF'
[program:%%group%%_octane]
command=%%php_bin%% %%root%%/current/artisan %%octane_server_command%% --host=127.0.0.1 --port=%%octane_port%% --workers=%%octane_workers%% %%octane_task_workers_option%%
directory=%%root%%/current
user=%%run_user%%
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=%%root%%/shared/storage/logs/octane.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF, [
            'group' => $group,
            'php_bin' => $phpBin,
            'root' => $deployRoot,
            'octane_server_command' => $octaneServerCommand,
            'octane_port' => $octanePort,
            'octane_workers' => $octaneWorkers,
            'octane_task_workers_option' => $octaneTaskWorkersOption,
            'run_user' => $runUser,
        ]));
    }

    if ($horizonEnabled) {
        $addSupervisorProgram("{$group}_horizon", $replaceVars(<<<'CONF'
[program:%%group%%_horizon]
command=%%php_bin%% %%root%%/current/artisan horizon
directory=%%root%%/current
user=%%run_user%%
autostart=true
autorestart=true
stopwaitsecs=3600
stopasgroup=true
killasgroup=true
stdout_logfile=%%root%%/shared/storage/logs/horizon.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF, ['group' => $group, 'php_bin' => $phpBin, 'root' => $deployRoot, 'run_user' => $runUser]));
    }

    if ($queueWorkerEnabled) {
        $addSupervisorProgram("{$group}_queue_worker", $replaceVars(<<<'CONF'
[program:%%group%%_queue_worker]
process_name=%(program_name)s_%(process_num)02d
command=%%php_bin%% %%root%%/current/artisan queue:work %%queue_connection%% --queue=%%queue_queue%% --sleep=3 --tries=3 --timeout=60 --max-time=3600
directory=%%root%%/current
user=%%run_user%%
numprocs=%%queue_processes%%
autostart=true
autorestart=true
stopwaitsecs=3600
stopasgroup=true
killasgroup=true
stdout_logfile=%%root%%/shared/storage/logs/queue_worker.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF, [
            'group' => $group,
            'php_bin' => $phpBin,
            'root' => $deployRoot,
            'queue_connection' => $queueWorkerConnection,
            'queue_queue' => $queueWorkerQueue,
            'queue_processes' => $queueWorkerProcesses,
            'run_user' => $runUser,
        ]));
    }

    if ($reverbEnabled) {
        $addSupervisorProgram("{$group}_reverb", $replaceVars(<<<'CONF'
[program:%%group%%_reverb]
command=%%php_bin%% %%root%%/current/artisan reverb:start
directory=%%root%%/current
user=%%run_user%%
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=%%root%%/shared/storage/logs/reverb.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF, ['group' => $group, 'php_bin' => $phpBin, 'root' => $deployRoot, 'run_user' => $runUser]));
    }

    if ($schedulerEnabled) {
        $addSupervisorProgram("{$group}_scheduler", $replaceVars(<<<'CONF'
[program:%%group%%_scheduler]
command=%%php_bin%% %%root%%/current/artisan schedule:work
directory=%%root%%/current
user=%%run_user%%
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=%%root%%/shared/storage/logs/scheduler.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF, ['group' => $group, 'php_bin' => $phpBin, 'root' => $deployRoot, 'run_user' => $runUser]));
    }

    if ($nightwatchEnabled) {
        $addSupervisorProgram("{$group}_nightwatch", $replaceVars(<<<'CONF'
[program:%%group%%_nightwatch]
command=%%php_bin%% %%root%%/current/artisan nightwatch:agent --listen-on=127.0.0.1:%%nightwatch_port%% --server=%%domain%% --silent
directory=%%root%%/current
user=%%run_user%%
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=%%root%%/shared/storage/logs/nightwatch.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF, [
            'group' => $group,
            'php_bin' => $phpBin,
            'root' => $deployRoot,
            'nightwatch_port' => $nightwatchPort,
            'domain' => $domain,
            'run_user' => $runUser,
        ]));
    }

    $hasSupervisorPrograms = $supervisorProgramNames !== [];
    $supervisorPrograms = $hasSupervisorPrograms
        ? implode(PHP_EOL.PHP_EOL, $supervisorDefinitions).PHP_EOL.PHP_EOL."[group:{$group}]".PHP_EOL.'programs='.implode(',', $supervisorProgramNames).PHP_EOL
        : '';

    $stubVars = [
        'group' => $group,
        'domain' => $domain,
        'root' => $deployRoot,
        'run_user' => $runUser,
        'php_bin' => $phpBin,
        'octane_port' => $octanePort,
        'ssl_email' => $sslEmail,
        'reverb_location' => $reverbLocation,
        'service_worker_location' => $serviceWorkerLocation,
        'application_locations' => $applicationLocations,
        'supervisor_programs' => $supervisorPrograms,
    ];

    $renderedNginxConf = is_file($nginxStub) ? $renderStub($nginxStub, $stubVars) : '';
    $renderedNginxSslConf = is_file($nginxSslStub) ? $renderStub($nginxSslStub, $stubVars) : '';
    $renderedSupervisorConf = $hasSupervisorPrograms && is_file($supervisorStub) ? $renderStub($supervisorStub, $stubVars) : '';

    $localTmp = sys_get_temp_dir().'/accelerator-bootstrap-'.bin2hex(random_bytes(4));
    $localNginx = $localTmp.'-nginx.conf';
    $localNginxSsl = $localTmp.'-nginx-ssl.conf';
    $localSupervisor = $localTmp.'-supervisor.conf';
    if ($renderedNginxConf !== '') {
        @mkdir(dirname($localNginx), 0700, true);
        file_put_contents($localNginx, $renderedNginxConf);
    }
    if ($renderedNginxSslConf !== '') {
        @mkdir(dirname($localNginxSsl), 0700, true);
        file_put_contents($localNginxSsl, $renderedNginxSslConf);
    }
    if ($renderedSupervisorConf !== '') {
        @mkdir(dirname($localSupervisor), 0700, true);
        file_put_contents($localSupervisor, $renderedSupervisorConf);
    }
@endsetup

{{-- ════════════════════════════════════════════════════════════════════
     Stories
     ──────────────────────────────────────────────────────────────────── --}}

@story('init')
    prepare-layout
    ensure-deploy-tools
    sync-env
    clone-release
    link-shared
    build-release
    harden-release
    prepare-laravel
    switch-current
    invalidate-opcache
    restart-service
    health-check
@endstory

@story('deploy')
    ensure-deploy-tools
    sync-env
    clone-release
    link-shared
    build-release
    harden-release
    clear-cache
    migration-safety
    db-backup
    maintenance-on
    prepare-laravel
    switch-current
    invalidate-opcache
    restart-service
    health-check
    maintenance-off
    prune-releases
@endstory

@story('deploy-slim')
    ensure-deploy-tools
    sync-env
    clone-release
    link-shared
    harden-release
    clear-cache
    migration-safety
    db-backup
    maintenance-on
    prepare-laravel
    switch-current
    invalidate-opcache
    restart-service
    health-check
    maintenance-off
    prune-releases
@endstory

@story('deploy-fresh-seed')
    assert-fresh-seed-confirmed
    ensure-deploy-tools
    sync-env
    clone-release
    link-shared
    build-release-with-dev
    harden-release
    clear-cache
    db-backup
    maintenance-on
    prepare-laravel-fresh-seed
    prune-dev-dependencies
    switch-current
    invalidate-opcache
    restart-service
    health-check
    maintenance-off
    prune-releases
@endstory

@story('status')
    check-status
@endstory

@story('restart')
    restart-service
@endstory

@story('logs')
    view-logs
@endstory

@story('rollback')
    rollback-release
    invalidate-current-opcache
    restart-service
    health-check
@endstory

@story('releases')
    list-releases
@endstory

@story('bootstrap')
    bootstrap-nginx
    bootstrap-supervisor
@endstory

@story('bootstrap-ssl')
    obtain-cert
    upgrade-nginx-ssl
@endstory

@story('backups')
    list-backups
@endstory

{{-- ════════════════════════════════════════════════════════════════════
     Layout & tooling
     ──────────────────────────────────────────────────────────────────── --}}

@task('prepare-layout', ['on' => 'vps'])
    set -euo pipefail
    mkdir -p {{ $deployRoot }} {{ $releasesPath }} {{ $sharedPath }} {{ $archivePath }} \
        {{ $sharedPath }}/storage/app/public \
        {{ $sharedPath }}/storage/framework/views \
        {{ $sharedPath }}/storage/framework/cache \
        {{ $sharedPath }}/storage/framework/sessions \
        {{ $sharedPath }}/storage/logs \
        {{ $sharedPath }}/database

    # Ensure deploy root is owned by deploy user
    sudo chown -R $(whoami):{{ $runUser }} {{ $deployRoot }}

    # Set default ACL on shared storage so www-data can write from first boot
    sudo setfacl -R -m u:{{ $runUser }}:rwx -m u:$(whoami):rwx {{ $sharedPath }}/storage
    sudo setfacl -dR -m u:{{ $runUser }}:rwx -m u:$(whoami):rwx {{ $sharedPath }}/storage

    # If SQLite, create database file with correct permissions
    if [ -f {{ $sharedPath }}/.env ]; then
        db_conn=$(grep -E "^DB_CONNECTION=" {{ $sharedPath }}/.env 2>/dev/null | cut -d= -f2 | tr -d '"' | tr -d "'" || true)
        if [ "$db_conn" = "sqlite" ]; then
            db_path=$(grep -E "^DB_DATABASE=" {{ $sharedPath }}/.env 2>/dev/null | cut -d= -f2 | tr -d '"' | tr -d "'" || true)
            if [ -n "$db_path" ] && [ ! -f "$db_path" ]; then
                mkdir -p "$(dirname "$db_path")"
                touch "$db_path"
                chmod 664 "$db_path"
                sudo setfacl -m u:{{ $runUser }}:rw -m u:$(whoami):rw "$db_path"
                echo "[prepare-layout] created SQLite database: $db_path"
            fi
            # Also ACL the database directory
            sudo setfacl -R -m u:{{ $runUser }}:rwx -m u:$(whoami):rwx {{ $sharedPath }}/database
            sudo setfacl -dR -m u:{{ $runUser }}:rwx -m u:$(whoami):rwx {{ $sharedPath }}/database
        fi
    fi

    test -d {{ $deployRoot }}
    test -d {{ $releasesPath }}
    test -d {{ $sharedPath }}
    test -d {{ $archivePath }}
    echo "[prepare-layout] layout ready at {{ $deployRoot }}"
@endtask

@task('sync-env', ['on' => 'localhost'])
    set -euo pipefail
    test -s {{ $seedEnvFile }}
    ssh {{ $sshHost }} 'set -euo pipefail; mkdir -p {{ $sharedPath }} {{ $archivePath }}; if [ -f {{ $sharedPath }}/.env ]; then cp {{ $sharedPath }}/.env {{ $archivePath }}/.env.before-seed-$(date +%Y-%m-%d_%H-%M-%S); fi'
    scp {{ $seedEnvFile }} {{ $sshHost }}:{{ $sharedPath }}/.env
    ssh {{ $sshHost }} 'set -euo pipefail; chmod 600 {{ $sharedPath }}/.env; sudo setfacl -m u:{{ $runUser }}:r {{ $sharedPath }}/.env'
@endtask

@task('ensure-deploy-tools', ['on' => 'vps'])
    set -euo pipefail
    command -v git >/dev/null
    command -v composer >/dev/null
    command -v {{ $phpBin }} >/dev/null
    command -v {{ $npmBin }} >/dev/null
    command -v larahelp >/dev/null
    command -v setfacl >/dev/null
    command -v curl >/dev/null
    test -f {{ $sharedPath }}/.env
@endtask

{{-- ════════════════════════════════════════════════════════════════════
     Release build
     ──────────────────────────────────────────────────────────────────── --}}

@task('clone-release', ['on' => 'vps'])
    set -euo pipefail
    mkdir -p {{ $releasesPath }} {{ $archivePath }}
    if [ -e {{ $releasePath }} ]; then
        echo "Release already exists: {{ $releasePath }}"
        exit 1
    fi
    git clone --branch {{ $branch }} --single-branch --depth 1 {{ $repo }} {{ $releasePath }}
    cd {{ $releasePath }}
    git fetch --depth 1 origin {{ $releaseSha }} 2>/dev/null || true
    git reset --hard {{ $releaseSha }}
@endtask

@task('link-shared', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $releasePath }}

    if [ -e .env ] || [ -L .env ]; then
        mv .env {{ $archivePath }}/release-env-{{ $releaseId }}
    fi
    ln -s {{ $sharedPath }}/.env .env

    if [ -e storage ] || [ -L storage ]; then
        mv storage {{ $archivePath }}/release-storage-{{ $releaseId }}
    fi
    ln -s {{ $sharedPath }}/storage storage

    mkdir -p {{ $sharedPath }}/storage/app/public public
    if [ -e public/storage ] || [ -L public/storage ]; then
        mv public/storage {{ $archivePath }}/release-public-storage-{{ $releaseId }}
    fi
    ln -s {{ $sharedPath }}/storage/app/public public/storage
@endtask

@task('build-release', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $releasePath }}
    mkdir -p resources/svg
    test -s composer.lock || { echo "[build-release] composer.lock is required; refusing dependency resolution during deployment."; exit 1; }
    composer validate --no-check-all --strict --ansi
    composer install --no-dev --no-scripts --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --quiet --ansi
    @if(str_contains($npmBin, 'bun'))
        {{ $npmBin }} install --frozen-lockfile --no-scripts --quiet
        {{ $npmBin }} run build
    @elseif(str_contains($npmBin, 'pnpm'))
        {{ $npmBin }} install --frozen-lockfile --no-scripts --quiet
        {{ $npmBin }} run build
    @else
        {{ $npmBin }} ci --no-audit --no-fund --quiet
        {{ $npmBin }} run build
    @endif
@endtask

@task('build-release-with-dev', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $releasePath }}
    mkdir -p resources/svg
    test -s composer.lock || { echo "[build-release-with-dev] composer.lock is required; refusing dependency resolution during deployment."; exit 1; }
    composer validate --no-check-all --strict --ansi
    composer install --no-scripts --no-interaction --no-progress --quiet --ansi
    @if(str_contains($npmBin, 'bun'))
        {{ $npmBin }} install --frozen-lockfile --no-scripts --quiet
        {{ $npmBin }} run build
    @elseif(str_contains($npmBin, 'pnpm'))
        {{ $npmBin }} install --frozen-lockfile --no-scripts --quiet
        {{ $npmBin }} run build
    @else
        {{ $npmBin }} ci --no-audit --no-fund --quiet
        {{ $npmBin }} run build
    @endif
@endtask

@task('harden-release', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $releasePath }}
    # Vendor is intentionally excluded: composer install already sets correct 644/755
    # and re-traversing thousands of vendor files is expensive with no benefit.
    find . -type d -not -path "./storage*" -not -path "./bootstrap/cache*" -not -path "./vendor*" -exec chmod 755 {} +
    find . -type f -not -path "./storage*" -not -path "./bootstrap/cache*" -not -path "./vendor*" -not -name "artisan" -exec chmod 644 {} +
    chmod 755 artisan
    chmod 600 {{ $sharedPath }}/.env
    sudo setfacl -m u:{{ $runUser }}:r {{ $sharedPath }}/.env
@endtask

{{-- ════════════════════════════════════════════════════════════════════
     Risky zone — past this line, production state is touched
     ──────────────────────────────────────────────────────────────────── --}}

@task('clear-cache', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $currentPath }}
    {{ $phpBin }} artisan optimize:clear --no-interaction --ansi
@endtask

@task('db-backup', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $currentPath }}
    {{ $phpBin }} artisan backup:run \
        --config=backup_predeploy \
        --only-db \
        --disable-notifications \
        --no-interaction \
        --ansi
@endtask

@task('assert-fresh-seed-confirmed', ['on' => 'localhost'])
    set -euo pipefail
    @if($freshSeedConfirmation !== $freshSeedConfirmationPhrase)
        echo "[deploy-fresh-seed] missing explicit destructive confirmation flag."
        exit 1
    @endif
    echo "[deploy-fresh-seed] destructive confirmation accepted for {{ $stage }}."
@endtask

@task('migration-safety', ['on' => 'vps'])
    set -euo pipefail
    # Pre-flight scan for destructive migration ops in the NEW release vs current.
    # Runs before db-backup so the operator can abort cheaply.
    # Heuristic-only: greps for dropColumn / dropTable / renameColumn / drop( in
    # migration files modified or added since the current release's git SHA.
    new_dir={{ $releasePath }}
    cur_dir="$(readlink -f {{ $currentPath }} 2>/dev/null || true)"
    if [ -z "$cur_dir" ] || [ ! -d "$cur_dir/database/migrations" ]; then
        echo "[migration-safety] no current symlink — skip (init flow)"
        exit 0
    fi
    # Diff migration filenames; if a migration file is in NEW but not in CURRENT, scan it.
    new_files=$(ls -1 "$new_dir/database/migrations" 2>/dev/null || true)
    cur_files=$(ls -1 "$cur_dir/database/migrations" 2>/dev/null || true)
    added=$(comm -23 <(echo "$new_files" | sort) <(echo "$cur_files" | sort) || true)
    if [ -z "$added" ]; then
        echo "[migration-safety] no new migrations vs current — pass"
        exit 0
    fi
    flagged=0
    while IFS= read -r f; do
        [ -z "$f" ] && continue
        path="$new_dir/database/migrations/$f"
        # Match exact destructive APIs only — avoid false positives like "dropdown".
        if grep -E '->\s*(dropColumn|dropTable|renameColumn|drop)\b|Schema::\s*(drop|dropIfExists|rename)\b' "$path" >/dev/null 2>&1; then
            echo "[migration-safety] DESTRUCTIVE op in $f"
            grep -nE '->\s*(dropColumn|dropTable|renameColumn|drop)\b|Schema::\s*(drop|dropIfExists|rename)\b' "$path" || true
            flagged=$((flagged+1))
        fi
    done <<< "$added"
    if [ "$flagged" -gt 0 ]; then
        echo "[migration-safety] $flagged new migration(s) contain destructive ops."
        echo "[migration-safety] Pre-deploy backup will run next, but review before letting it through."
        echo "[migration-safety] Set MIGRATION_SAFETY_ALLOW=1 in .env.envoy to ack and proceed."
        if ! grep -E '^MIGRATION_SAFETY_ALLOW=(1|true|yes|on)' {{ $sharedPath }}/.env >/dev/null 2>&1; then
            cd_envoy="$(grep -E '^MIGRATION_SAFETY_ALLOW' {{ $sharedPath }}/.env 2>/dev/null || true)"
            echo "[migration-safety] aborted (current env: ${cd_envoy:-unset})"
            exit 1
        fi
        echo "[migration-safety] MIGRATION_SAFETY_ALLOW set — proceeding."
    fi
    echo "[migration-safety] scanned $(echo "$added" | wc -l) new migration(s) — pass"
@endtask

@task('maintenance-on', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $currentPath }}
    {{ $phpBin }} artisan down \
        --secret={{ $maintenanceSecret }} \
        --redirect=/ \
        --no-interaction \
        --ansi
    echo ""
    echo "──────────────────────────────────────────────────────────────"
    echo "  Maintenance bypass URL: https://{{ $domain }}/{{ $maintenanceSecret }}"
    echo "  Visit ONCE to set bypass cookie, then preview while deploy continues."
    echo "──────────────────────────────────────────────────────────────"
    echo ""
@endtask

@task('prepare-laravel', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $releasePath }}
    larahelp --reoptimize
    larahelp --setfacl
    {{ $phpBin }} artisan migrate --force --no-interaction --ansi
    {{ $phpBin }} artisan storage:link --force --no-interaction --ansi
@endtask

@task('prepare-laravel-fresh-seed', ['on' => 'vps'])
    set -euo pipefail
    @if($freshSeedConfirmation !== $freshSeedConfirmationPhrase)
        echo "[deploy-fresh-seed] missing explicit destructive confirmation flag."
        exit 1
    @endif
    cd {{ $releasePath }}
    larahelp --setfacl
    (
        export APP_ENV=local
        trap 'unset APP_ENV' EXIT
        larahelp --reoptimize
        {{ $phpBin }} artisan migrate:fresh --seed --force --no-interaction --ansi
    )
    {{ $phpBin }} artisan storage:link --force --no-interaction --ansi
@endtask

@task('prune-dev-dependencies', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $releasePath }}
    test -s composer.lock || { echo "[prune-dev-dependencies] composer.lock is required; refusing dependency resolution during deployment."; exit 1; }
    composer install --no-dev --no-scripts --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --quiet --ansi
    larahelp --reoptimize
    larahelp --setfacl
@endtask

@task('switch-current', ['on' => 'vps'])
    set -euo pipefail
    if [ -e {{ $nextSymlink }} ] || [ -L {{ $nextSymlink }} ]; then
        mv {{ $nextSymlink }} {{ $archivePath }}/current.next.{{ $releaseId }}
    fi
    ln -s {{ $releasePath }} {{ $nextSymlink }}
    if [ -e {{ $currentPath }} ] || [ -L {{ $currentPath }} ]; then
        mv {{ $currentPath }} {{ $archivePath }}/current.before-{{ $releaseId }}
    fi
    mv {{ $nextSymlink }} {{ $currentPath }}
@endtask

@task('invalidate-opcache', ['on' => 'vps'])
    set -euo pipefail
    {{ $phpBin }} -r '$root = $argv[1]; if (! function_exists("opcache_invalidate")) { exit(0); } $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)); foreach ($iterator as $file) { if ($file->isFile() && $file->getExtension() === "php") { opcache_invalidate($file->getPathname(), true); } }' {{ $releasePath }}
@endtask

@task('invalidate-current-opcache', ['on' => 'vps'])
    set -euo pipefail
    {{ $phpBin }} -r '$root = $argv[1]; if (! function_exists("opcache_invalidate")) { exit(0); } $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)); foreach ($iterator as $file) { if ($file->isFile() && $file->getExtension() === "php") { opcache_invalidate($file->getPathname(), true); } }' {{ $currentPath }}
@endtask

@task('restart-service', ['on' => 'vps'])
    set -euo pipefail
    @if(! $hasSupervisorPrograms)
        echo "[restart-service] No Supervisor-managed programs enabled for {{ $stage }}."
    @else
        @if($service === 'all')
            sudo supervisorctl restart {{ $group }}:*
            sleep 2
            # Fail fast if an enabled program cannot boot after deployment.
            if sudo supervisorctl status {{ $group }}:* | grep -E '\sFATAL\s' >/dev/null 2>&1; then
                echo "[restart-service] One or more programs are FATAL after restart:"
                sudo supervisorctl status {{ $group }}:*
                exit 1
            fi
        @else
            sudo supervisorctl restart {{ $group }}:{{ $group }}_{{ $service }}
            sleep 2
            if sudo supervisorctl status {{ $group }}:{{ $group }}_{{ $service }} | grep -E '\sFATAL\s' >/dev/null 2>&1; then
                echo "[restart-service] {{ $service }} is FATAL after restart."
                exit 1
            fi
        @endif
    @endif
@endtask

@task('health-check', ['on' => 'vps'])
    set -euo pipefail
    @if($httpRuntime === 'octane')
        # Curl Octane directly to validate the long-running application process.
        status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 -H "Host: {{ $domain }}" http://127.0.0.1:{{ $octanePort }}/up || echo "000")
        if [ "$status" != "200" ]; then
            echo "[health-check] /up returned HTTP $status from Octane at 127.0.0.1:{{ $octanePort }} (expected 200)."
            echo "[health-check] App did NOT boot cleanly. Maintenance mode is still ON if this was deploy."
            exit 1
        fi
        echo "[health-check] OK: Octane at 127.0.0.1:{{ $octanePort }}/up returned 200."
    @else
        # FPM is managed by the OS service; validate it through the active Nginx vhost.
        status=$(curl -L -s -o /dev/null -w "%{http_code}" --max-time 8 --resolve "{{ $domain }}:80:127.0.0.1" --resolve "{{ $domain }}:443:127.0.0.1" http://{{ $domain }}/up || echo "000")
        if [ "$status" != "200" ]; then
            echo "[health-check] /up returned HTTP $status through Nginx/PHP-FPM for {{ $domain }} (expected 200)."
            echo "[health-check] App did NOT boot cleanly. Maintenance mode is still ON if this was deploy."
            exit 1
        fi
        echo "[health-check] OK: Nginx/PHP-FPM served {{ $domain }}/up with HTTP 200."
    @endif
@endtask

@task('maintenance-off', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $currentPath }}
    {{ $phpBin }} artisan up --no-interaction --ansi
@endtask

@task('prune-releases', ['on' => 'vps'])
    set -euo pipefail
    keep={{ $keepReleases }}
    if [ "$keep" -lt 1 ]; then
        echo "[prune-releases] OPS_DEPLOY_KEEP_RELEASES=$keep is invalid (must be >=1). Skip."
        exit 0
    fi
    current="$(readlink -f {{ $currentPath }} 2>/dev/null || true)"
    # Sort releases newest-first, keep $keep including current. Anything beyond is pruned.
    # `current` is always preserved even if it would have fallen off the list.
    mapfile -t all_releases < <(find {{ $releasesPath }} -mindepth 1 -maxdepth 1 -type d | sort -r)
    kept=0
    pruned=0
    for r in "${all_releases[@]}"; do
        if [ "$r" = "$current" ]; then
            kept=$((kept+1))
            continue
        fi
        if [ "$kept" -lt "$keep" ]; then
            kept=$((kept+1))
            continue
        fi
        echo "[prune-releases] removing $(basename "$r")"
        rm -rf "$r"
        pruned=$((pruned+1))
    done
    echo "[prune-releases] kept=$kept pruned=$pruned (target keep=$keep)"
@endtask

{{-- ════════════════════════════════════════════════════════════════════
     Diagnostics, rollback, listing
     ──────────────────────────────────────────────────────────────────── --}}

@task('check-status', ['on' => 'vps'])
    set -euo pipefail
    echo "Stage: {{ $stage }}"
    echo "Domain: {{ $domain }}"
    echo "Root: {{ $deployRoot }}"
    echo "HTTP runtime: {{ $httpRuntime }}"
    echo "Current: $(readlink -f {{ $currentPath }} 2>/dev/null || true)"
    echo "Shared .env: $(test -f {{ $sharedPath }}/.env && echo present || echo missing)"
    @if($hasSupervisorPrograms)
        sudo supervisorctl status {{ $group }}:*
    @else
        echo "Supervisor programs: none enabled"
    @endif
@endtask

@task('view-logs', ['on' => 'vps'])
    set -euo pipefail
    @if($service === 'all')
        tail -f {{ $sharedPath }}/storage/logs/laravel.log
    @else
        tail -f {{ $sharedPath }}/storage/logs/{{ $service }}.log
    @endif
@endtask

@task('list-releases', ['on' => 'vps'])
    set -euo pipefail
    current="$(readlink -f {{ $currentPath }} 2>/dev/null || true)"
    keep={{ $keepReleases }}
    echo ""
    printf "%-44s %-10s %-6s %s\n" "RELEASE" "SIZE" "AGE" "STATUS"
    echo "──────────────────────────────────────────────────────────────────────────────"
    idx=0
    while IFS= read -r r; do
        rel_name="$(basename "$r")"
        size="$(du -sh "$r" 2>/dev/null | awk '{print $1}')"
        mtime="$(stat -c '%Y' "$r" 2>/dev/null || stat -f '%m' "$r")"
        now="$(date +%s)"
        age_s=$((now - mtime))
        if [ "$age_s" -lt 3600 ]; then
            age=$((age_s / 60))m
        elif [ "$age_s" -lt 86400 ]; then
            age=$((age_s / 3600))h
        else
            age=$((age_s / 86400))d
        fi
        if [ "$r" = "$current" ]; then
            status="CURRENT"
        elif [ "$idx" -lt "$keep" ]; then
            status="kept"
        else
            status="will-prune"
        fi
        printf "%-44s %-10s %-6s %s\n" "$rel_name" "$size" "$age" "$status"
        idx=$((idx+1))
    done < <(find {{ $releasesPath }} -mindepth 1 -maxdepth 1 -type d | sort -r)
    echo ""
    echo "OPS_DEPLOY_KEEP_RELEASES=${keep}"
@endtask

@task('rollback-release', ['on' => 'vps', 'confirm' => true])
    set -euo pipefail
    current="$(readlink -f {{ $currentPath }} 2>/dev/null || true)"
    target=""
    # Pick the newest release that is NOT current AND has a complete build
    # (vendor/autoload.php and .env symlink both present).
    while IFS= read -r r; do
        if [ "$r" = "$current" ]; then
            continue
        fi
        if [ ! -f "$r/vendor/autoload.php" ]; then
            echo "[rollback] skipping incomplete release $(basename "$r") (no vendor/autoload.php)"
            continue
        fi
        if [ ! -L "$r/.env" ]; then
            echo "[rollback] skipping incomplete release $(basename "$r") (no .env symlink)"
            continue
        fi
        target="$r"
        break
    done < <(find {{ $releasesPath }} -mindepth 1 -maxdepth 1 -type d | sort -r)

    if [ -z "$target" ]; then
        echo "[rollback] No valid previous release found. Aborting."
        exit 1
    fi

    echo "[rollback] target: $(basename "$target")"

    if [ -e {{ $rollbackSymlink }} ] || [ -L {{ $rollbackSymlink }} ]; then
        mv {{ $rollbackSymlink }} {{ $archivePath }}/current.rollback.$(date +%Y-%m-%d_%H-%M-%S)
    fi
    ln -s "$target" {{ $rollbackSymlink }}
    if [ -e {{ $currentPath }} ] || [ -L {{ $currentPath }} ]; then
        mv {{ $currentPath }} {{ $archivePath }}/current.before-rollback-$(date +%Y-%m-%d_%H-%M-%S)
    fi
    mv {{ $rollbackSymlink }} {{ $currentPath }}
    echo "[rollback] current -> $(basename "$target")"
@endtask


{{-- ════════════════════════════════════════════════════════════════════
     Backup listing
     ──────────────────────────────────────────────────────────────────── --}}

@task('list-backups', ['on' => 'vps'])
    set -euo pipefail
    app_name=$(grep -E "^APP_NAME=" {{ $sharedPath }}/.env | cut -d= -f2 | tr -d '"' | tr -d "'")
    backup_base="{{ $sharedPath }}/storage/app/private"

    echo ""
    echo "═══════════════════════════════════════════════════════════════"
    echo "  PREDEPLOY BACKUPS: ${app_name}-predeploy"
    echo "═══════════════════════════════════════════════════════════════"
    predeploy_dir="${backup_base}/${app_name}-predeploy"
    if [ -d "$predeploy_dir" ]; then
        find "$predeploy_dir" -type f -name "*.zip" -printf "%T@ %s %p\n" 2>/dev/null | sort -rn | head -10 | while read -r modified size path; do
            size_kb=$((size / 1024))
            printf "  %6s KB  %s\n" "$size_kb" "$(basename "$path")"
        done
    else
        echo "  (none)"
    fi

    echo ""
    echo "═══════════════════════════════════════════════════════════════"
    echo "  SCHEDULED BACKUPS: ${app_name}"
    echo "═══════════════════════════════════════════════════════════════"
    scheduled_dir="${backup_base}/${app_name}"
    if [ -d "$scheduled_dir" ]; then
        find "$scheduled_dir" -type f -name "*.zip" -printf "%T@ %s %p\n" 2>/dev/null | sort -rn | head -10 | while read -r modified size path; do
            size_kb=$((size / 1024))
            printf "  %6s KB  %s\n" "$size_kb" "$(basename "$path")"
        done
    else
        echo "  (none)"
    fi
    echo ""
@endtask

{{-- ════════════════════════════════════════════════════════════════════
     One-shot VPS bootstrap: nginx vhost + supervisor conf
     ──────────────────────────────────────────────────────────────────── --}}

@task('bootstrap-nginx', ['on' => 'localhost'])
    set -euo pipefail

    # Determine which stub to use: if cert already exists on VPS, use SSL stub
    has_cert=$(ssh {{ $sshHost }} 'sudo test -f /etc/letsencrypt/live/{{ $domain }}/fullchain.pem && echo yes || echo no')

    if [ "$has_cert" = "yes" ]; then
        test -s {{ $localNginxSsl }}
        conf_file="{{ $localNginxSsl }}"
        echo "[bootstrap-nginx] SSL cert detected — using SSL+QUIC stub"
    else
        test -s {{ $localNginx }}
        conf_file="{{ $localNginx }}"
        echo "[bootstrap-nginx] No SSL cert — using HTTP-only stub"
    fi

    # Guard: if existing config has ssl_certificate, warn and require confirmation
    has_ssl_config=$(ssh {{ $sshHost }} 'sudo grep -q "ssl_certificate" /etc/nginx/sites-available/{{ $domain }}.conf 2>/dev/null && echo yes || echo no')
    if [ "$has_ssl_config" = "yes" ] && [ "$has_cert" = "no" ]; then
        echo "[bootstrap-nginx] WARNING: existing config has SSL but no cert found."
        echo "[bootstrap-nginx] Skipping to avoid downgrading SSL config. Fix the certificate path or restore the archived vhost."
        exit 0
    fi

    echo "[bootstrap-nginx] uploading rendered {{ $httpRuntime }} vhost to {{ $sshHost }}…"
    scp "$conf_file" {{ $sshHost }}:/tmp/{{ $domain }}.conf
    ssh {{ $sshHost }} 'set -euo pipefail
        sudo mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
        if [ -f /etc/nginx/sites-available/{{ $domain }}.conf ]; then
            sudo mkdir -p {{ $archivePath }}
            sudo cp /etc/nginx/sites-available/{{ $domain }}.conf {{ $archivePath }}/nginx-{{ $domain }}.conf.before-bootstrap-$(date +%Y-%m-%d_%H-%M-%S)
        fi
        sudo mv /tmp/{{ $domain }}.conf /etc/nginx/sites-available/{{ $domain }}.conf
        sudo chown root:root /etc/nginx/sites-available/{{ $domain }}.conf
        sudo chmod 644 /etc/nginx/sites-available/{{ $domain }}.conf
        sudo ln -sfn /etc/nginx/sites-available/{{ $domain }}.conf /etc/nginx/sites-enabled/{{ $domain }}.conf
        sudo nginx -t
        sudo systemctl reload nginx
    '

    if [ "$has_cert" = "yes" ]; then
        echo "[bootstrap-nginx] SSL vhost live for {{ $domain }} (HTTPS + HTTP/2 + HTTP/3)"
    else
        echo "[bootstrap-nginx] HTTP-only vhost live for {{ $domain }}."
        echo "[bootstrap-nginx] To enable SSL, run:"
        echo "  vendor/bin/envoy run bootstrap-ssl --stage={{ $stage }}"
    fi

    rm -f {{ $localNginx }} {{ $localNginxSsl }}
@endtask

@task('bootstrap-supervisor', ['on' => 'localhost'])
    set -euo pipefail
    @if($hasSupervisorPrograms)
        test -s {{ $localSupervisor }}
        echo "[bootstrap-supervisor] uploading rendered conf to {{ $sshHost }}…"
        scp {{ $localSupervisor }} {{ $sshHost }}:/tmp/{{ $group }}.conf
        ssh {{ $sshHost }} 'set -euo pipefail
            sudo mkdir -p /etc/supervisor/conf.d
            if [ -f /etc/supervisor/conf.d/{{ $group }}.conf ]; then
                sudo mkdir -p {{ $archivePath }}
                sudo cp /etc/supervisor/conf.d/{{ $group }}.conf {{ $archivePath }}/supervisor-{{ $group }}.conf.before-bootstrap-$(date +%Y-%m-%d_%H-%M-%S)
            fi
            sudo mv /tmp/{{ $group }}.conf /etc/supervisor/conf.d/{{ $group }}.conf
            sudo chown root:root /etc/supervisor/conf.d/{{ $group }}.conf
            sudo chmod 644 /etc/supervisor/conf.d/{{ $group }}.conf
            sudo supervisorctl reread
            sudo supervisorctl update
            echo "[bootstrap-supervisor] {{ $group }} group registered. Programs:"
            sudo supervisorctl status {{ $group }}:* || true
        '
        rm -f {{ $localSupervisor }}
    @else
        echo "[bootstrap-supervisor] No managed services enabled; removing any old {{ $group }} config."
        ssh {{ $sshHost }} 'set -euo pipefail
            if [ -f /etc/supervisor/conf.d/{{ $group }}.conf ]; then
                sudo mkdir -p {{ $archivePath }}
                sudo cp /etc/supervisor/conf.d/{{ $group }}.conf {{ $archivePath }}/supervisor-{{ $group }}.conf.before-disable-$(date +%Y-%m-%d_%H-%M-%S)
                sudo rm /etc/supervisor/conf.d/{{ $group }}.conf
                sudo supervisorctl reread
                sudo supervisorctl update
            fi
        '
    @endif
@endtask

@task('obtain-cert', ['on' => 'localhost'])
    set -euo pipefail
    # Check if cert already exists
    has_cert=$(ssh {{ $sshHost }} 'sudo test -f /etc/letsencrypt/live/{{ $domain }}/fullchain.pem && echo yes || echo no')
    if [ "$has_cert" = "yes" ]; then
        echo "[obtain-cert] Certificate already exists for {{ $domain }}. Skipping."
        exit 0
    fi

    # Ensure webroot path exists
    ssh {{ $sshHost }} 'sudo mkdir -p {{ $deployRoot }}/current/public/.well-known/acme-challenge'

    echo "[obtain-cert] Requesting certificate via certbot webroot..."
    ssh {{ $sshHost }} 'sudo certbot certonly \
        --webroot \
        -w {{ $deployRoot }}/current/public \
        -d {{ $domain }} \
        -m {{ $sslEmail }} \
        --agree-tos \
        --non-interactive'

    echo "[obtain-cert] Certificate obtained for {{ $domain }}."
@endtask

@task('upgrade-nginx-ssl', ['on' => 'localhost'])
    set -euo pipefail
    # Verify cert exists after obtain-cert
    has_cert=$(ssh {{ $sshHost }} 'sudo test -f /etc/letsencrypt/live/{{ $domain }}/fullchain.pem && echo yes || echo no')
    if [ "$has_cert" != "yes" ]; then
        echo "[upgrade-nginx-ssl] No certificate found. Run obtain-cert first or check certbot output."
        exit 1
    fi

    test -s {{ $localNginxSsl }}
    echo "[upgrade-nginx-ssl] Uploading SSL vhost config..."
    scp {{ $localNginxSsl }} {{ $sshHost }}:/tmp/{{ $domain }}.conf
    ssh {{ $sshHost }} 'set -euo pipefail
        sudo mkdir -p {{ $archivePath }}
        if [ -f /etc/nginx/sites-available/{{ $domain }}.conf ]; then
            sudo cp /etc/nginx/sites-available/{{ $domain }}.conf {{ $archivePath }}/nginx-{{ $domain }}.conf.before-ssl-$(date +%Y-%m-%d_%H-%M-%S)
        fi
        sudo mv /tmp/{{ $domain }}.conf /etc/nginx/sites-available/{{ $domain }}.conf
        sudo chown root:root /etc/nginx/sites-available/{{ $domain }}.conf
        sudo chmod 644 /etc/nginx/sites-available/{{ $domain }}.conf
        sudo ln -sfn /etc/nginx/sites-available/{{ $domain }}.conf /etc/nginx/sites-enabled/{{ $domain }}.conf
        sudo nginx -t
        sudo systemctl reload nginx
    '
    echo "[upgrade-nginx-ssl] SSL vhost live for {{ $domain }} (HTTPS + HTTP/2 + HTTP/3)"
    rm -f {{ $localNginxSsl }}
@endtask
