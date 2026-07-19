{{-- WireNinja Accelerator v2 deployment bridge. Laravel is never booted here. --}}

@setup
    $projectRoot = getcwd();
    $requestedTask = isset($__task) ? (string) $__task : '';
    $requestedStage = isset($stage) && is_string($stage) ? $stage : null;
    $config = \WireNinja\Accelerator\Deployment\DeploymentConfig::load($projectRoot, $requestedStage);
    $renderer = new \WireNinja\Accelerator\Deployment\DeploymentRenderer($config);
    $truthy = static fn (mixed $value): bool => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);

    $releaseSha = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));
    if (preg_match('/^[a-f0-9]{40}$/', $releaseSha) !== 1) {
        throw new RuntimeException('Deployment requires a Git commit at the project root.');
    }

    $releaseShortSha = substr($releaseSha, 0, 8);
    $initialRelease = $requestedTask === 'init';
    $freshSeed = $requestedTask === 'deploy-fresh-seed';
    $releaseId = $initialRelease
        ? 'init_'.$releaseSha
        : date('Y-m-d_H-i-s').'_'.$releaseShortSha.'_'.bin2hex(random_bytes(2)).($freshSeed ? '_fresh' : '');
    $releasePath = $config->releasesPath().'/'.$releaseId;
    $releaseEnvironmentPath = $config->sharedPath().'/env/'.$releaseId.'.env';
    $nextSymlink = $config->deployRoot.'/current.next';
    $rollbackSymlink = $config->deployRoot.'/current.rollback';
    $maintenanceOwner = $config->sharedPath().'/.accelerator-maintenance-owner';
    $allowDestructiveMigrations = $truthy($allowDestructiveMigrations ?? false);
    $freshSeedConfirmationPhrase = 'aku mengkonfirmasi remigrate fresh seed';
    $freshSeedConfirmation = (string) ($iUnderstandThisWillDropAndReseedDatabase ?? '');

    if ($freshSeed && $freshSeedConfirmation !== $freshSeedConfirmationPhrase) {
        throw new RuntimeException(
            'Refusing destructive fresh-seed deploy. Re-run with --i-understand-this-will-drop-and-reseed-database="'.
            $freshSeedConfirmationPhrase.'".'
        );
    }

    $service = isset($service) ? (string) $service : 'all';
    if (! in_array($service, ['all', 'octane', 'horizon', 'queue', 'reverb', 'scheduler', 'nightwatch'], true)) {
        throw new InvalidArgumentException("Unsupported service [{$service}].");
    }

    $nginxHttp = $renderer->nginx(false);
    $nginxSsl = $renderer->nginx(true);
    $supervisor = $renderer->supervisor();
    $nginxHttpBase64 = base64_encode($nginxHttp);
    $nginxSslBase64 = base64_encode($nginxSsl);
    $supervisorBase64 = base64_encode($supervisor);
    $nginxHttpHash = hash('sha256', $nginxHttp);
    $nginxSslHash = hash('sha256', $nginxSsl);
    $supervisorHash = hash('sha256', $supervisor);
    $hasSupervisorPrograms = $config->hasSupervisorPrograms();

    $runtimeEnvironmentFile = escapeshellarg($config->runtimeEnvironmentFile());
    $repository = escapeshellarg($config->repository);
    $branch = escapeshellarg($config->branch);
    $sshHost = escapeshellarg($config->sshHost);
    $adminName = escapeshellarg($config->adminName);
    $adminUsername = escapeshellarg($config->adminUsername);
    $adminEmail = escapeshellarg($config->adminEmail);
    $adminPasswordHash = escapeshellarg($config->adminPasswordHash);
    $localPhp = escapeshellarg(PHP_BINARY);
@endsetup

@story('init')
    local-preflight
    remote-preflight
    dns-preflight
    prepare-layout
    upload-env
    stage-env
    install-infrastructure
    clone-release
    link-shared
    build-release
    harden-release
    activate-env
    prepare-release
    switch-current
    activate-runtime
    health-check
    ensure-ssl
    https-check
    prune-releases
@endstory

@story('deploy')
    local-preflight
    remote-preflight
    infrastructure-drift
    prepare-layout
    upload-env
    stage-env
    clone-release
    link-shared
    build-release
    harden-release
    migration-safety
    db-backup
    maintenance-on
    activate-env
    prepare-release
    switch-current
    activate-runtime
    health-check
    maintenance-off
    prune-releases
@endstory

@story('deploy-fresh-seed')
    local-preflight
    remote-preflight
    infrastructure-drift
    prepare-layout
    upload-env
    stage-env
    clone-release
    link-shared
    build-release
    harden-release
    db-backup
    maintenance-on
    activate-env
    prepare-release
    prune-dev-dependencies
    switch-current
    activate-runtime
    health-check
    maintenance-off
    prune-releases
@endstory

@story('preflight')
    local-preflight
    remote-preflight
    dns-preflight
@endstory

@story('bootstrap')
    remote-preflight
    dns-preflight
    prepare-layout
    install-infrastructure
    activate-runtime
    health-check
@endstory

@story('ssl')
    remote-preflight
    dns-preflight
    ensure-ssl
    https-check
@endstory

@story('restart')
    remote-preflight
    activate-runtime
    health-check
@endstory

@story('rollback')
    rollback-release
    activate-runtime
    health-check
    clear-deploy-maintenance
@endstory

@story('status')
    check-status
@endstory

@story('logs')
    view-logs
@endstory

@story('releases')
    list-releases
@endstory

@story('backups')
    list-backups
@endstory

@story('render')
    render-config
@endstory

@task('local-preflight', ['on' => 'localhost'])
    set -Eeuo pipefail
    cd {{ escapeshellarg($projectRoot) }}

    runtime_env={{ $runtimeEnvironmentFile }}
    test -s "$runtime_env" || { echo "[preflight] Missing runtime seed: $runtime_env"; exit 1; }
    test -s composer.lock || { echo "[preflight] composer.lock is required."; exit 1; }
    test -s bun.lock || { echo "[preflight] bun.lock is required."; exit 1; }
    test -x vendor/bin/pint || { echo "[preflight] vendor/bin/pint is required."; exit 1; }
    for legacy_lock in package-lock.json pnpm-lock.yaml yarn.lock bun.lockb; do
        [ ! -e "$legacy_lock" ] || { echo "[preflight] Remove legacy frontend lock: $legacy_lock"; exit 1; }
    done
    package_manager="$({{ $localPhp }} -r '$package = json_decode(file_get_contents("package.json"), true, flags: JSON_THROW_ON_ERROR); echo $package["packageManager"] ?? "";')"
    case "$package_manager" in bun@*) ;; *) echo "[preflight] package.json must declare packageManager=bun@..."; exit 1 ;; esac

    if grep -Eq '^OPS_DEPLOY_' "$runtime_env"; then
        echo "[preflight] Runtime env must not contain OPS_DEPLOY_* keys."
        exit 1
    fi

    env_value() {
        sed -n "s/^${1}=//p" "$runtime_env" | tail -n 1 | sed -e 's/^"//' -e 's/"$//'
    }

    [ "$(env_value APP_ENV)" = "production" ] || { echo "[preflight] APP_ENV must be production."; exit 1; }
    [ "$(env_value APP_DEBUG)" = "false" ] || { echo "[preflight] APP_DEBUG must be false."; exit 1; }
    [ -n "$(env_value APP_KEY)" ] || { echo "[preflight] APP_KEY is blank in $runtime_env."; exit 1; }

    app_url="$(env_value APP_URL)"
    case "$app_url" in
        https://{{ $config->domain }}|https://{{ $config->domain }}/) ;;
        *) echo "[preflight] APP_URL must be https://{{ $config->domain }} for stage {{ $config->stage }}."; exit 1 ;;
    esac

    db_connection="$(env_value DB_CONNECTION)"
    [ -n "$db_connection" ] || { echo "[preflight] DB_CONNECTION is blank."; exit 1; }
    if [ "$db_connection" = "sqlite" ]; then
        expected_db={{ escapeshellarg($config->sharedPath().'/database/database.sqlite') }}
        [ "$(env_value DB_DATABASE)" = "$expected_db" ] || {
            echo "[preflight] SQLite DB_DATABASE must be $expected_db so releases share one database."
            exit 1
        }
    else
        [ -n "$(env_value DB_DATABASE)" ] || { echo "[preflight] DB_DATABASE is blank."; exit 1; }
        [ -n "$(env_value DB_USERNAME)" ] || { echo "[preflight] DB_USERNAME is blank."; exit 1; }
    fi

    for ignored in .env .env.envoy .env.staging .env.production; do
        if git ls-files --error-unmatch "$ignored" >/dev/null 2>&1; then
            echo "[preflight] $ignored contains local configuration and must not be tracked."
            exit 1
        fi
    done

    if [ -n "$(git status --porcelain)" ]; then
        echo "[preflight] Git worktree is dirty. Commit or restore every change before deployment."
        git status --short
        exit 1
    fi

    vendor/bin/pint --format agent
    if [ -n "$(git status --porcelain)" ]; then
        echo "[preflight] Pint changed tracked files. Review and commit them before deployment."
        git status --short
        exit 1
    fi

    composer validate --no-check-publish --no-interaction

    current_branch="$(git branch --show-current)"
    [ "$current_branch" = {{ $branch }} ] || {
        echo "[preflight] Current branch [$current_branch] does not match configured branch {{ $config->branch }}."
        exit 1
    }

    local_sha="$(git rev-parse HEAD)"
    remote_sha="$(git ls-remote {{ $repository }} {{ $branch }} | awk 'NR == 1 { print $1 }')"
    [ -n "$remote_sha" ] || { echo "[preflight] Unable to resolve configured remote branch."; exit 1; }
    [ "$local_sha" = "$remote_sha" ] || {
        echo "[preflight] Commit $local_sha is not the remote head ($remote_sha). Push before deployment."
        exit 1
    }

    echo "[preflight] local configuration, locks, Git, Composer, and Pint are ready."
@endtask

@task('remote-preflight', ['on' => 'vps'])
    set -Eeuo pipefail
    sudo -n true >/dev/null || { echo "[preflight] Passwordless sudo is required for scoped Nginx/Supervisor operations."; exit 1; }

    for command in git composer {{ $config->phpBinary }} {{ $config->bunBinary }} curl base64 sha256sum setfacl nginx; do
        command -v "$command" >/dev/null || { echo "[preflight] Missing VPS command: $command"; exit 1; }
    done
    @if($hasSupervisorPrograms)
        command -v supervisorctl >/dev/null || { echo "[preflight] Missing VPS command: supervisorctl"; exit 1; }
    @endif
    @if($config->httpRuntime === 'octane' || $config->reverbEnabled || $config->nightwatchEnabled)
        command -v ss >/dev/null || { echo "[preflight] Missing VPS command: ss"; exit 1; }
    @endif
    @if(in_array($requestedTask, ['init', 'ssl'], true))
        command -v certbot >/dev/null || { echo "[preflight] Missing VPS command: certbot"; exit 1; }
    @endif

    actual_php="$({{ $config->phpBinary }} -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    [ "$actual_php" = "{{ $config->phpVersion }}" ] || {
        echo "[preflight] {{ $config->phpBinary }} is PHP $actual_php; expected {{ $config->phpVersion }}."
        exit 1
    }

    probe={{ $config->deployRoot }}
    while [ ! -d "$probe" ] && [ "$probe" != "/" ]; do probe="$(dirname "$probe")"; done
    free_mb="$(df -Pm "$probe" | awk 'NR == 2 { print $4 }')"
    [ "${free_mb:-0}" -ge 1024 ] || { echo "[preflight] Less than 1024 MB is free below $probe."; exit 1; }

    @if($config->httpRuntime === 'fpm')
        [ -S {{ $config->fpmSocket }} ] || { echo "[preflight] Missing FPM socket: {{ $config->fpmSocket }}"; exit 1; }
        systemctl status {{ $config->fpmService }} >/dev/null 2>&1 || { echo "[preflight] Missing FPM service: {{ $config->fpmService }}"; exit 1; }
    @endif

    @if(in_array($requestedTask, ['deploy', 'deploy-fresh-seed', 'bootstrap', 'ssl', 'restart'], true))
        current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
        [ -n "$current" ] && [ -f "$current/vendor/autoload.php" ] && [ -L "$current/.env" ] || {
            echo "[preflight] No valid current release. Run envoy init first."
            exit 1
        }
    @endif

    @if($requestedTask === 'init')
        current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
        if [ -n "$current" ] && [ "$current" != {{ escapeshellarg($releasePath) }} ]; then
            echo "[preflight] This stage was already initialized. Use envoy deploy for a new commit."
            exit 1
        fi
        if [ -z "$current" ]; then
            @if($config->httpRuntime === 'octane')
                ss -H -ltn "sport = :{{ $config->octanePort }}" | grep -q . && { echo "[preflight] Octane port {{ $config->octanePort }} is already in use."; exit 1; }
            @endif
            @if($config->reverbEnabled)
                ss -H -ltn "sport = :{{ $config->reverbPort }}" | grep -q . && { echo "[preflight] Reverb port {{ $config->reverbPort }} is already in use."; exit 1; }
            @endif
            @if($config->nightwatchEnabled)
                ss -H -ltn "sport = :{{ $config->nightwatchPort }}" | grep -q . && { echo "[preflight] Nightwatch port {{ $config->nightwatchPort }} is already in use."; exit 1; }
            @endif
        fi
    @endif

    echo "[preflight] remote tools, runtime, disk, and stage state are ready."
@endtask

@task('dns-preflight', ['on' => 'vps'])
    set -Eeuo pipefail
    dns_ips="$({{ $config->phpBinary }} -r '$records = dns_get_record($argv[1], DNS_A | DNS_AAAA); foreach ($records ?: [] as $record) { $ip = $record["ip"] ?? $record["ipv6"] ?? null; if (is_string($ip)) { echo $ip, PHP_EOL; } }' {{ escapeshellarg($config->domain) }})"
    [ -n "$dns_ips" ] || { echo "[dns] {{ $config->domain }} has no A or AAAA record."; exit 1; }

    server_ip="$(printf '%s\n' "${SSH_CONNECTION:-}" | awk '{ print $3 }')"
    @if($config->dnsDirect)
        [ -n "$server_ip" ] || { echo "[dns] Unable to determine the VPS address from SSH_CONNECTION."; exit 1; }
        printf '%s\n' "$dns_ips" | grep -Fx "$server_ip" >/dev/null || {
            echo "[dns] {{ $config->domain }} does not resolve directly to this VPS ($server_ip)."
            echo "[dns] Set OPS_DEPLOY_{{ strtoupper($config->stage) }}_DNS_DIRECT=false only when a deliberate reverse proxy is in front."
            exit 1
        }
    @endif

    echo "[dns] {{ $config->domain }} resolves and matches the configured topology."
@endtask

@task('prepare-layout', ['on' => 'vps'])
    set -Eeuo pipefail
    deploy_user="$(id -un)"
    deploy_group="$(id -gn)"

    sudo install -d -o "$deploy_user" -g "$deploy_group" -m 0755 \
        {{ $config->deployRoot }} {{ $config->releasesPath() }} {{ $config->archivePath() }} {{ $config->sharedPath() }}
    sudo install -d -o "$deploy_user" -g {{ $config->runUser }} -m 2775 \
        {{ $config->sharedPath() }}/storage/app/public \
        {{ $config->sharedPath() }}/storage/framework/cache \
        {{ $config->sharedPath() }}/storage/framework/sessions \
        {{ $config->sharedPath() }}/storage/framework/views \
        {{ $config->sharedPath() }}/storage/logs \
        {{ $config->sharedPath() }}/database \
        {{ $config->sharedPath() }}/acme/.well-known/acme-challenge
    sudo install -d -o "$deploy_user" -g {{ $config->runUser }} -m 0750 {{ $config->sharedPath() }}/env

    sudo setfacl -R -m u:"$deploy_user":rwx -m u:{{ $config->runUser }}:rwx {{ $config->sharedPath() }}/storage {{ $config->sharedPath() }}/database
    sudo setfacl -dR -m u:"$deploy_user":rwx -m u:{{ $config->runUser }}:rwx {{ $config->sharedPath() }}/storage {{ $config->sharedPath() }}/database
    echo "[layout] release layout ready at {{ $config->deployRoot }}."
@endtask

@task('upload-env', ['on' => 'localhost'])
    set -Eeuo pipefail
    test -s {{ $runtimeEnvironmentFile }}
    ssh {{ $sshHost }} 'umask 077; cat > /tmp/{{ $config->group }}-runtime-env' < {{ $runtimeEnvironmentFile }}
    echo "[env] uploaded the {{ $config->stage }} runtime seed to a temporary remote path."
@endtask

@task('stage-env', ['on' => 'vps'])
    set -Eeuo pipefail
    source=/tmp/{{ $config->group }}-runtime-env
    test -s "$source"
    if grep -Eq '^OPS_DEPLOY_' "$source"; then
        echo "[env] Refusing runtime env containing OPS_DEPLOY_* keys."
        exit 1
    fi
    @if($initialRelease)
        current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
        if [ "$current" = {{ escapeshellarg($releasePath) }} ] && [ -f {{ $releaseEnvironmentPath }} ] && ! cmp -s "$source" {{ $releaseEnvironmentPath }}; then
            echo "[env] init resume received a changed runtime env. Use envoy deploy so backup and maintenance protections apply."
            exit 1
        fi
    @endif
    candidate={{ $releaseEnvironmentPath }}.upload
    install -m 0600 "$source" "$candidate"
    mv -f "$candidate" {{ $releaseEnvironmentPath }}
    rm -f "$source"
    sudo setfacl -m u:{{ $config->runUser }}:r {{ $releaseEnvironmentPath }}
    echo "[env] staged immutable environment for release {{ $releaseId }}."
@endtask

@task('install-infrastructure', ['on' => 'vps'])
    set -Eeuo pipefail
    timestamp="$(date +%Y-%m-%d_%H-%M-%S)"
    nginx_target=/etc/nginx/sites-available/{{ $config->domain }}.conf
    nginx_link=/etc/nginx/sites-enabled/{{ $config->domain }}.conf
    nginx_candidate="/tmp/{{ $config->group }}-nginx-$$.conf"
    nginx_backup=""
    trap 'rm -f "$nginx_candidate" /tmp/{{ $config->group }}-supervisor-$$.conf' EXIT

    if sudo test -s /etc/letsencrypt/live/{{ $config->domain }}/fullchain.pem; then
        printf '%s' {{ escapeshellarg($nginxSslBase64) }} | base64 --decode > "$nginx_candidate"
    else
        printf '%s' {{ escapeshellarg($nginxHttpBase64) }} | base64 --decode > "$nginx_candidate"
    fi

    enabled_target="$(sudo readlink -f "$nginx_link" 2>/dev/null || true)"
    previous_link="$(sudo readlink "$nginx_link" 2>/dev/null || true)"
    previous_link_existed=0
    sudo test -L "$nginx_link" && previous_link_existed=1
    if ! sudo test -f "$nginx_target" || ! cmp -s "$nginx_candidate" "$nginx_target" || [ "$enabled_target" != "$nginx_target" ]; then
        if sudo test -f "$nginx_target"; then
            nginx_backup={{ $config->archivePath() }}/nginx-{{ $config->domain }}-before-$timestamp.conf
            sudo cp "$nginx_target" "$nginx_backup"
            sudo chown "$(id -un):$(id -gn)" "$nginx_backup"
        fi

        sudo install -o root -g root -m 0644 "$nginx_candidate" "$nginx_target"
        sudo ln -sfn "$nginx_target" "$nginx_link"
        if ! sudo nginx -t; then
            if [ -n "$nginx_backup" ]; then
                sudo cp "$nginx_backup" "$nginx_target"
            else
                sudo rm -f "$nginx_target"
            fi
            if [ "$previous_link_existed" = 1 ]; then
                sudo ln -sfn "$previous_link" "$nginx_link"
            else
                sudo rm -f "$nginx_link"
            fi
            sudo nginx -t || true
            echo "[bootstrap] Nginx candidate failed validation; the previous config was restored."
            exit 1
        fi
        sudo systemctl reload nginx
    fi

    supervisor_target=/etc/supervisor/conf.d/{{ $config->group }}.conf
    supervisor_backup=""
    supervisor_changed=0
    @if($hasSupervisorPrograms)
        supervisor_candidate="/tmp/{{ $config->group }}-supervisor-$$.conf"
        printf '%s' {{ escapeshellarg($supervisorBase64) }} | base64 --decode > "$supervisor_candidate"
        if ! sudo test -f "$supervisor_target" || ! cmp -s "$supervisor_candidate" "$supervisor_target"; then
            if sudo test -f "$supervisor_target"; then
                supervisor_backup={{ $config->archivePath() }}/supervisor-{{ $config->group }}-before-$timestamp.conf
                sudo cp "$supervisor_target" "$supervisor_backup"
                sudo chown "$(id -un):$(id -gn)" "$supervisor_backup"
            fi
            sudo install -o root -g root -m 0644 "$supervisor_candidate" "$supervisor_target"
            supervisor_changed=1
        fi
    @else
        if sudo test -f "$supervisor_target"; then
            command -v supervisorctl >/dev/null || { echo "[bootstrap] supervisorctl is required to retire the old scoped config."; exit 1; }
            supervisor_backup={{ $config->archivePath() }}/supervisor-{{ $config->group }}-before-disable-$timestamp.conf
            sudo cp "$supervisor_target" "$supervisor_backup"
            sudo chown "$(id -un):$(id -gn)" "$supervisor_backup"
            sudo rm -f "$supervisor_target"
            supervisor_changed=1
        fi
    @endif

    @if($hasSupervisorPrograms)
        if [ "$supervisor_changed" = 1 ] && ! sudo supervisorctl reread; then
            if [ -n "$supervisor_backup" ]; then
                sudo cp "$supervisor_backup" "$supervisor_target"
            else
                sudo rm -f "$supervisor_target"
            fi
            sudo supervisorctl reread || true
            echo "[bootstrap] Supervisor candidate failed validation; the previous config was restored."
            exit 1
        fi
    @else
        if [ "$supervisor_changed" = 1 ]; then
            if ! sudo supervisorctl reread || ! sudo supervisorctl update; then
                sudo cp "$supervisor_backup" "$supervisor_target"
                sudo supervisorctl reread || true
                sudo supervisorctl update || true
                echo "[bootstrap] Unable to retire the scoped Supervisor config; the previous file was restored."
                exit 1
            fi
        fi
    @endif

    echo "[bootstrap] scoped Nginx and Supervisor candidates installed; service activation is deferred until a valid current release exists."
@endtask

@task('infrastructure-drift', ['on' => 'vps'])
    set -Eeuo pipefail
    nginx_target=/etc/nginx/sites-available/{{ $config->domain }}.conf
    nginx_link=/etc/nginx/sites-enabled/{{ $config->domain }}.conf
    if sudo test -s /etc/letsencrypt/live/{{ $config->domain }}/fullchain.pem; then expected_nginx={{ $nginxSslHash }}; else expected_nginx={{ $nginxHttpHash }}; fi
    actual_nginx="$(sudo sha256sum "$nginx_target" 2>/dev/null | awk '{ print $1 }')"
    enabled_target="$(sudo readlink -f "$nginx_link" 2>/dev/null || true)"
    [ "$actual_nginx" = "$expected_nginx" ] && [ "$enabled_target" = "$nginx_target" ] || {
        echo "[preflight] Nginx config drift detected. Run: vendor/bin/envoy run bootstrap --stage={{ $config->stage }}"
        exit 1
    }

    supervisor_target=/etc/supervisor/conf.d/{{ $config->group }}.conf
    @if($hasSupervisorPrograms)
        actual_supervisor="$(sudo sha256sum "$supervisor_target" 2>/dev/null | awk '{ print $1 }')"
        [ "$actual_supervisor" = "{{ $supervisorHash }}" ] || {
            echo "[preflight] Supervisor config drift detected. Run: vendor/bin/envoy run bootstrap --stage={{ $config->stage }}"
            exit 1
        }
    @else
        if sudo test -e "$supervisor_target"; then
            echo "[preflight] Obsolete scoped Supervisor config exists. Run envoy bootstrap."
            exit 1
        fi
    @endif

    echo "[preflight] deployed infrastructure matches the v2 renderer."
@endtask

@task('clone-release', ['on' => 'vps'])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    if [ "$current" = {{ escapeshellarg($releasePath) }} ] && [ -f {{ $releasePath }}/.accelerator-prepared ]; then
        echo "[release] initial release already prepared; resuming init."
        exit 0
    fi

    if [ -e {{ $releasePath }} ]; then
        existing_sha="$(git -C {{ $releasePath }} rev-parse HEAD 2>/dev/null || true)"
        if [ "$existing_sha" = "{{ $releaseSha }}" ]; then
            echo "[release] reusing incomplete release {{ $releaseId }}."
            exit 0
        fi
        mv {{ $releasePath }} {{ $config->archivePath() }}/incomplete-{{ $releaseId }}-$(date +%Y-%m-%d_%H-%M-%S)
    fi

    git clone --branch {{ $branch }} --single-branch --depth=1 {{ $repository }} {{ $releasePath }}
    actual_sha="$(git -C {{ $releasePath }} rev-parse HEAD)"
    [ "$actual_sha" = "{{ $releaseSha }}" ] || {
        echo "[release] cloned $actual_sha, expected {{ $releaseSha }}."
        exit 1
    }
    echo "[release] cloned exact commit {{ $releaseSha }}."
@endtask

@task('link-shared', ['on' => 'vps'])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    if [ "$current" = {{ escapeshellarg($releasePath) }} ] && [ -f {{ $releasePath }}/.accelerator-prepared ]; then
        exit 0
    fi

    test -s {{ $releaseEnvironmentPath }}
    cd {{ $releasePath }}
    rm -f .env
    ln -s {{ $releaseEnvironmentPath }} .env

    rm -rf -- storage
    ln -s {{ $config->sharedPath() }}/storage storage
    mkdir -p public bootstrap/cache
    rm -rf -- public/storage
    ln -s {{ $config->sharedPath() }}/storage/app/public public/storage

    db_connection="$(sed -n 's/^DB_CONNECTION=//p' {{ $releaseEnvironmentPath }} | tail -n 1 | tr -d '"')"
    if [ "$db_connection" = "sqlite" ]; then
        database={{ $config->sharedPath() }}/database/database.sqlite
        touch "$database"
        chmod 0660 "$database"
        sudo setfacl -m u:{{ $config->runUser }}:rw "$database"
    fi

    sudo setfacl -R -m u:{{ $config->runUser }}:rwx bootstrap/cache
    sudo setfacl -dR -m u:{{ $config->runUser }}:rwx bootstrap/cache
    echo "[release] linked staged env and shared writable state."
@endtask

@task('build-release', ['on' => 'vps'])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    if [ "$current" = {{ escapeshellarg($releasePath) }} ] && [ -f {{ $releasePath }}/.accelerator-prepared ]; then
        echo "[build] prepared initial release already exists; skipping rebuild."
        exit 0
    fi

    cd {{ $releasePath }}
    test -s composer.lock || { echo "[build] composer.lock is required."; exit 1; }
    test -s bun.lock || { echo "[build] bun.lock is required."; exit 1; }
    composer validate --no-check-publish --no-interaction
    @if($freshSeed)
        composer install --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
    @else
        composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
    @endif
    {{ $config->bunBinary }} install --frozen-lockfile
    {{ $config->bunBinary }} run build
    rm -rf -- {{ $releasePath }}/node_modules
    printf '%s\n' {{ escapeshellarg($releaseSha) }} > .accelerator-build-complete
    echo "[build] locked Composer and Bun build completed."
@endtask

@task('harden-release', ['on' => 'vps'])
    set -Eeuo pipefail
    chmod 0755 {{ $releasePath }} {{ $releasePath }}/artisan {{ $releasePath }}/public {{ $releasePath }}/bootstrap/cache
    chmod 0600 {{ $releaseEnvironmentPath }}
    sudo setfacl -m u:{{ $config->runUser }}:r {{ $releaseEnvironmentPath }}
    sudo setfacl -R -m u:{{ $config->runUser }}:rwx {{ $releasePath }}/bootstrap/cache
    sudo setfacl -dR -m u:{{ $config->runUser }}:rwx {{ $releasePath }}/bootstrap/cache
    echo "[release] permissions hardened without traversing vendor or source trees."
@endtask

@task('migration-safety', ['on' => 'vps'])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    [ -n "$current" ] && [ -d "$current/database/migrations" ] || { echo "[migrations] no current migration tree; skipped."; exit 0; }

    flagged=0
    while IFS= read -r -d '' migration; do
        relative="${migration#{{ $releasePath }}/database/migrations/}"
        old="$current/database/migrations/$relative"
        if [ -f "$old" ] && cmp -s "$migration" "$old"; then continue; fi
        if grep -nE -- '->\s*(dropColumn|renameColumn|drop)\b|Schema::\s*(drop|dropIfExists|rename)\b' "$migration"; then
            echo "[migrations] destructive operation found in $relative"
            flagged=$((flagged + 1))
        fi
    done < <(find {{ $releasePath }}/database/migrations -type f -name '*.php' -print0 2>/dev/null)

    if [ "$flagged" -gt 0 ] && [ "{{ $allowDestructiveMigrations ? '1' : '0' }}" != "1" ]; then
        echo "[migrations] Refusing $flagged added or modified destructive migration(s)."
        echo "[migrations] Review them, then re-run with --allow-destructive-migrations=true when intentional."
        exit 1
    fi
    echo "[migrations] added and modified migration scan passed."
@endtask

@task('db-backup', ['on' => 'vps'])
    set -Eeuo pipefail
    cd {{ $config->currentPath() }}
    {{ $config->phpBinary }} artisan backup:run --config=backup_predeploy --only-db --disable-notifications --no-interaction --ansi
    echo "[backup] pre-deploy database backup completed against the still-active env."
@endtask

@task('maintenance-on', ['on' => 'vps'])
    set -Eeuo pipefail
    marker={{ $config->sharedPath() }}/storage/framework/down
    if [ -f "$marker" ] && [ ! -f {{ $maintenanceOwner }} ]; then
        echo "[maintenance] Existing maintenance marker is not owned by Envoy; refusing to overwrite it."
        exit 1
    fi
    cd {{ $config->currentPath() }}
    {{ $config->phpBinary }} artisan down --retry=60 --refresh=15 --no-interaction --ansi
    printf '%s\n' {{ escapeshellarg($releaseId) }} > {{ $maintenanceOwner }}
    echo "[maintenance] Nginx now blocks application, static, and websocket traffic before PHP."
@endtask

@task('activate-env', ['on' => 'vps'])
    set -Eeuo pipefail
    active={{ $config->sharedPath() }}/.env
    candidate={{ $releaseEnvironmentPath }}
    test -s "$candidate"

    if [ -e "$active" ] || [ -L "$active" ]; then
        previous="$(readlink -f "$active" 2>/dev/null || printf '%s' "$active")"
        test -f "$previous"
        cp "$previous" {{ $config->archivePath() }}/.env-before-{{ $releaseId }}
        chmod 0600 {{ $config->archivePath() }}/.env-before-{{ $releaseId }}
    fi

    active_next={{ $config->sharedPath() }}/.env.next
    rm -f "$active_next"
    ln -s "$candidate" "$active_next"
    mv -Tf "$active_next" "$active"
    echo "[env] shared .env now points to the release-specific environment."
@endtask

@task('prepare-release', ['on' => 'vps'])
    set -Eeuo pipefail
    cd {{ $releasePath }}
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    if [ "$current" = {{ escapeshellarg($releasePath) }} ] && [ -f .accelerator-prepared ]; then
        {{ $config->phpBinary }} artisan optimize --no-interaction --ansi
        echo "[laravel] refreshed caches for resumed init."
        exit 0
    fi

    {{ $config->phpBinary }} artisan optimize:clear --no-interaction --ansi
    @if($freshSeed)
        {{ $config->phpBinary }} -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); Illuminate\Support\Facades\DB::prohibitDestructiveCommands(false); $status = $kernel->call("migrate:fresh", ["--seed" => true, "--force" => true, "--no-interaction" => true, "--ansi" => true]); echo $kernel->output(); exit($status);'
    @else
        {{ $config->phpBinary }} artisan migrate --force --no-interaction --ansi
    @endif

    @if(($initialRelease || $freshSeed) && $config->initialAdminEnabled)
        {{ $config->phpBinary }} artisan accelerator:provision-admin \
            --name={{ $adminName }} \
            --username={{ $adminUsername }} \
            --email={{ $adminEmail }} \
            --password-hash={{ $adminPasswordHash }} \
            --no-interaction
    @endif

    {{ $config->phpBinary }} artisan storage:link --force --no-interaction --ansi
    {{ $config->phpBinary }} artisan optimize --no-interaction --ansi
    printf '%s\n' {{ escapeshellarg($releaseSha) }} > .accelerator-prepared
    echo "[laravel] migration, bootstrap identity, storage, and caches are ready."
@endtask

@task('prune-dev-dependencies', ['on' => 'vps'])
    set -Eeuo pipefail
    cd {{ $releasePath }}
    composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
    find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete
    {{ $config->phpBinary }} artisan optimize --no-interaction --ansi
    echo "[build] development dependencies removed after explicit fresh seed."
@endtask

@task('switch-current', ['on' => 'vps'])
    set -Eeuo pipefail
    test -f {{ $releasePath }}/vendor/autoload.php
    test -L {{ $releasePath }}/.env
    test -f {{ $releasePath }}/.accelerator-prepared

    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    if [ "$current" = {{ escapeshellarg($releasePath) }} ]; then
        echo "[switch] current already points to {{ $releaseId }}."
        exit 0
    fi

    if [ -n "$current" ]; then
        ln -s "$current" {{ $config->archivePath() }}/current-before-{{ $releaseId }}
    elif [ -e {{ $config->currentPath() }} ] && [ ! -L {{ $config->currentPath() }} ]; then
        mv {{ $config->currentPath() }} {{ $config->archivePath() }}/legacy-current-before-{{ $releaseId }}
    fi

    rm -f {{ $nextSymlink }}
    ln -s {{ $releasePath }} {{ $nextSymlink }}
    mv -Tf {{ $nextSymlink }} {{ $config->currentPath() }}
    echo "[switch] current -> {{ $releaseId }}."
@endtask

@task('activate-runtime', ['on' => 'vps'])
    set -Eeuo pipefail
    @if($hasSupervisorPrograms)
        sudo supervisorctl reread
        sudo supervisorctl update
        @if($service === 'all')
            sudo supervisorctl restart {{ $config->group }}:*
            target={{ escapeshellarg($config->group.':*') }}
        @else
            sudo supervisorctl restart {{ $config->group }}:{{ $config->group }}_{{ $service }}
            target={{ escapeshellarg($config->group.':'.$config->group.'_'.$service) }}
        @endif

        for attempt in $(seq 1 15); do
            status="$(sudo supervisorctl status "$target" 2>&1 || true)"
            if printf '%s\n' "$status" | grep -Eq '\b(FATAL|BACKOFF|EXITED|STOPPED|UNKNOWN)\b'; then
                printf '%s\n' "$status"
                echo "[runtime] A configured process failed to start."
                exit 1
            fi
            if [ -n "$status" ] && ! printf '%s\n' "$status" | grep -Ev '\bRUNNING\b' >/dev/null; then break; fi
            sleep 1
        done
        sudo supervisorctl status "$target"
    @endif

    @if($config->httpRuntime === 'fpm')
        echo "[runtime] PHP-FPM stays scoped and running; the new realpath creates distinct OPcache keys without reloading unrelated pools."
    @else
        echo "[runtime] enabled stage services restarted; process-owned OPcache state was replaced."
    @endif
    echo "[runtime] stage runtime activation completed."
@endtask

@task('health-check', ['on' => 'vps'])
    set -Eeuo pipefail
    if sudo test -s /etc/letsencrypt/live/{{ $config->domain }}/fullchain.pem; then
        scheme=https
        resolve="--resolve {{ $config->domain }}:443:127.0.0.1"
    else
        scheme=http
        resolve="--resolve {{ $config->domain }}:80:127.0.0.1"
    fi

    status=000
    for attempt in $(seq 1 20); do
        status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 5 $resolve "$scheme://{{ $config->domain }}/up" || true)"
        [ "$status" = "200" ] && break
        sleep 1
    done

    if [ "$status" != "200" ]; then
        echo "[health] /up returned HTTP ${status:-000}; deploy-owned maintenance remains active."
        @if($hasSupervisorPrograms)
            sudo supervisorctl status {{ $config->group }}:* || true
        @endif
        tail -n 80 {{ $config->sharedPath() }}/storage/logs/laravel.log 2>/dev/null || true
        exit 1
    fi
    echo "[health] Nginx and {{ $config->httpRuntime }} served /up with HTTP 200."
@endtask

@task('ensure-ssl', ['on' => 'vps'])
    set -Eeuo pipefail
    challenge=""
    candidate=""
    cleanup_ssl() {
        [ -z "$challenge" ] || rm -f "$challenge"
        [ -z "$candidate" ] || rm -f "$candidate"
    }
    report_partial() {
        code=$?
        trap - ERR EXIT
        cleanup_ssl
        if [ "$code" -ne 0 ]; then
            echo "[ssl] PARTIAL_READY: the application is healthy over HTTP, but HTTPS setup failed."
            echo "[ssl] Resume with: vendor/bin/envoy run ssl --stage={{ $config->stage }}"
        fi
        exit "$code"
    }
    trap report_partial ERR
    trap cleanup_ssl EXIT

    if ! sudo test -s /etc/letsencrypt/live/{{ $config->domain }}/fullchain.pem; then
        challenge={{ $config->sharedPath() }}/acme/.well-known/acme-challenge/accelerator-preflight
        printf '%s\n' "{{ $releaseId }}" > "$challenge"
        body="$(curl -fsS --max-time 8 http://{{ $config->domain }}/.well-known/acme-challenge/accelerator-preflight)"
        rm -f "$challenge"
        [ "$body" = "{{ $releaseId }}" ] || { echo "[ssl] Public ACME webroot check failed."; false; }

        sudo certbot certonly --webroot \
            -w {{ $config->sharedPath() }}/acme \
            -d {{ $config->domain }} \
            -m {{ $config->sslEmail }} \
            --agree-tos --non-interactive --keep-until-expiring
    fi

    target=/etc/nginx/sites-available/{{ $config->domain }}.conf
    candidate="/tmp/{{ $config->group }}-nginx-ssl-$$.conf"
    backup={{ $config->archivePath() }}/nginx-{{ $config->domain }}-before-ssl-$(date +%Y-%m-%d_%H-%M-%S).conf
    printf '%s' {{ escapeshellarg($nginxSslBase64) }} | base64 --decode > "$candidate"

    if ! cmp -s "$candidate" "$target"; then
        sudo cp "$target" "$backup"
        sudo chown "$(id -un):$(id -gn)" "$backup"
        sudo install -o root -g root -m 0644 "$candidate" "$target"
        if ! sudo nginx -t; then
            sudo cp "$backup" "$target"
            sudo nginx -t || true
            echo "[ssl] SSL Nginx candidate failed; previous config restored."
            false
        fi
        sudo systemctl reload nginx
    fi

    trap - ERR
    echo "[ssl] valid certificate and rendered HTTPS vhost are active."
@endtask

@task('https-check', ['on' => 'vps'])
    set -Eeuo pipefail
    local_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 8 --resolve {{ $config->domain }}:443:127.0.0.1 https://{{ $config->domain }}/up || true)"
    public_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 12 https://{{ $config->domain }}/up || true)"
    [ "$local_status" = "200" ] && [ "$public_status" = "200" ] || {
        echo "[ssl] HTTPS verification failed (local=$local_status public=$public_status)."
        exit 1
    }
    echo "[ssl] local and public HTTPS health checks returned 200."
@endtask

@task('maintenance-off', ['on' => 'vps'])
    set -Eeuo pipefail
    if [ -f {{ $maintenanceOwner }} ] && [ "$(cat {{ $maintenanceOwner }})" = "{{ $releaseId }}" ]; then
        cd {{ $config->currentPath() }}
        {{ $config->phpBinary }} artisan up --no-interaction --ansi
        rm -f {{ $maintenanceOwner }}
        echo "[maintenance] deploy-owned marker removed."
    else
        echo "[maintenance] no marker owned by this deploy; nothing removed."
    fi
@endtask

@task('clear-deploy-maintenance', ['on' => 'vps'])
    set -Eeuo pipefail
    if [ -f {{ $maintenanceOwner }} ]; then
        cd {{ $config->currentPath() }}
        {{ $config->phpBinary }} artisan up --no-interaction --ansi
        rm -f {{ $maintenanceOwner }}
        echo "[maintenance] stale deploy-owned marker cleared after healthy rollback."
    fi
@endtask

@task('prune-releases', ['on' => 'vps'])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    mapfile -t releases < <(find {{ $config->releasesPath() }} -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2-)
    kept=0
    pruned=0
    for release in "${releases[@]}"; do
        case "$release" in {{ $config->releasesPath() }}/*) ;; *) echo "[prune] unsafe release path: $release"; exit 1 ;; esac
        if [ "$release" = "$current" ]; then kept=$((kept + 1)); continue; fi
        if [ "$kept" -lt {{ $config->keepReleases }} ]; then kept=$((kept + 1)); continue; fi
        release_env="$(readlink -f "$release/.env" 2>/dev/null || true)"
        echo "[prune] removing old release $(basename "$release")"
        rm -rf -- "$release"
        case "$release_env" in
            {{ $config->sharedPath() }}/env/*.env)
                if ! find {{ $config->releasesPath() }} -mindepth 2 -maxdepth 2 -type l -name .env -exec readlink -f {} \; | grep -Fx "$release_env" >/dev/null; then
                    rm -f -- "$release_env"
                fi
                ;;
        esac
        pruned=$((pruned + 1))
    done
    echo "[prune] kept=$kept pruned=$pruned; archive evidence was not touched."
@endtask

@task('rollback-release', ['on' => 'vps', 'confirm' => true])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    target=""
    while IFS= read -r candidate; do
        [ "$candidate" = "$current" ] && continue
        if [ -f "$candidate/vendor/autoload.php" ] && [ -L "$candidate/.env" ] && [ -f "$candidate/.accelerator-prepared" ]; then
            target="$candidate"
            break
        fi
    done < <(find {{ $config->releasesPath() }} -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2-)

    [ -n "$target" ] || { echo "[rollback] No complete previous release exists."; exit 1; }
    case "$target" in {{ $config->releasesPath() }}/*) ;; *) echo "[rollback] unsafe target."; exit 1 ;; esac

    target_env="$(readlink -f "$target/.env")"
    case "$target_env" in {{ $config->sharedPath() }}/env/*.env) ;; *) echo "[rollback] unsafe release env target."; exit 1 ;; esac
    test -s "$target_env"

    rm -f {{ $rollbackSymlink }}
    ln -s "$target" {{ $rollbackSymlink }}
    if [ -n "$current" ]; then
        ln -s "$current" {{ $config->archivePath() }}/current-before-rollback-$(date +%Y-%m-%d_%H-%M-%S)
    fi
    mv -Tf {{ $rollbackSymlink }} {{ $config->currentPath() }}
    rm -f {{ $config->sharedPath() }}/.env.next
    ln -s "$target_env" {{ $config->sharedPath() }}/.env.next
    mv -Tf {{ $config->sharedPath() }}/.env.next {{ $config->sharedPath() }}/.env
    echo "[rollback] current -> $(basename "$target"). Database rollback remains manual by design."
@endtask

@task('check-status', ['on' => 'vps'])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    echo "stage={{ $config->stage }}"
    echo "domain={{ $config->domain }}"
    echo "root={{ $config->deployRoot }}"
    echo "runtime={{ $config->httpRuntime }}"
    echo "current=${current:-missing}"
    echo "runtime_env=$(test -s {{ $config->sharedPath() }}/.env && echo present || echo missing)"
    echo "maintenance=$(test -f {{ $config->sharedPath() }}/storage/framework/down && echo active || echo inactive)"
    echo "maintenance_owner=$(test -f {{ $maintenanceOwner }} && echo envoy || echo none)"
    echo "certificate=$(sudo test -s /etc/letsencrypt/live/{{ $config->domain }}/fullchain.pem && echo present || echo missing)"
    sudo nginx -t
    @if($hasSupervisorPrograms)
        sudo supervisorctl status {{ $config->group }}:* || true
    @endif
@endtask

@task('view-logs', ['on' => 'vps'])
    set -Eeuo pipefail
    @if($service === 'all')
        log={{ $config->sharedPath() }}/storage/logs/laravel.log
    @else
        log={{ $config->sharedPath() }}/storage/logs/{{ $service }}.log
    @endif
    test -f "$log" || { echo "[logs] Missing log: $log"; exit 1; }
    tail -n 200 "$log"
@endtask

@task('list-releases', ['on' => 'vps'])
    set -Eeuo pipefail
    current="$(readlink -f {{ $config->currentPath() }} 2>/dev/null || true)"
    printf '%-56s %-10s %s\n' RELEASE SIZE STATUS
    while IFS= read -r release; do
        status=available
        [ "$release" = "$current" ] && status=CURRENT
        [ -f "$release/.accelerator-prepared" ] || status=incomplete
        size="$(du -sh "$release" | awk '{ print $1 }')"
        printf '%-56s %-10s %s\n' "$(basename "$release")" "$size" "$status"
    done < <(find {{ $config->releasesPath() }} -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2-)
@endtask

@task('list-backups', ['on' => 'vps'])
    set -Eeuo pipefail
    base={{ $config->sharedPath() }}/storage/app/private
    find "$base" -type f -name '*.zip' -printf '%T@\t%s\t%p\n' 2>/dev/null | sort -rn | head -20 | while IFS=$'\t' read -r modified bytes path; do
        printf '%10s KB  %s\n' "$((bytes / 1024))" "$path"
    done
@endtask

@task('render-config', ['on' => 'localhost'])
    set -Eeuo pipefail
    echo "===== NGINX HTTP ====="
    {{ $localPhp }} -r 'echo base64_decode($argv[1]);' {{ escapeshellarg($nginxHttpBase64) }}
    echo "===== NGINX HTTPS ====="
    {{ $localPhp }} -r 'echo base64_decode($argv[1]);' {{ escapeshellarg($nginxSslBase64) }}
    echo "===== SUPERVISOR ====="
    @if($hasSupervisorPrograms)
        {{ $localPhp }} -r 'echo base64_decode($argv[1]);' {{ escapeshellarg($supervisorBase64) }}
    @else
        echo "(no Supervisor-managed programs)"
    @endif
@endtask
