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
    rollback    Atomic switch back to a verified previous release. NO maintenance
                 window — emergency speed prioritised.
    releases     Print release history + current pointer + prune target.
    status, restart, logs unchanged.
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
    $npmBin = $value($envoy, "OPS_DEPLOY_{$stageKey}_NPM_BIN", $value($envoy, 'OPS_DEPLOY_NPM_BIN', 'npm'));
    $runUser = $value($envoy, "OPS_DEPLOY_{$stageKey}_RUN_USER", $value($envoy, 'OPS_DEPLOY_RUN_USER', 'www-data'));
    $sshHost = $value($envoy, "OPS_DEPLOY_{$stageKey}_SSH_HOST", $value($envoy, 'OPS_DEPLOY_SSH_HOST', 'onidel'));
    $octanePort = $value($envoy, "OPS_DEPLOY_{$stageKey}_OCTANE_PORT");
    $keepReleases = (int) $value($envoy, 'OPS_DEPLOY_KEEP_RELEASES', '5');
    $service = isset($service) ? $service : 'all';
    $seedEnvFile = $root.'/'.($stage === 'prod' ? '.env.production' : '.env.staging');

    foreach (['domain' => $domain, 'root' => $deployRoot, 'repo' => $repo, 'group' => $group, 'octane port' => $octanePort] as $name => $required) {
        if ($required === '') {
            throw new RuntimeException("Missing OPS deploy {$name} for stage [{$stage}] in .env.envoy.");
        }
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
    $nginxStub = $root.'/vendor/wireninja/accelerator/stubs/vps/nginx-vhost.conf.stub';
    if (! is_file($nginxStub)) {
        $nginxStub = $root.'/packages/accelerator/stubs/vps/nginx-vhost.conf.stub';
    }
    $supervisorStub = $root.'/vendor/wireninja/accelerator/stubs/vps/supervisor.conf.stub';
    if (! is_file($supervisorStub)) {
        $supervisorStub = $root.'/packages/accelerator/stubs/vps/supervisor.conf.stub';
    }

    $reverbPort = $value($envoy, "OPS_DEPLOY_{$stageKey}_REVERB_PORT", '0');
    $sslEmail = $value($envoy, 'OPS_DEPLOY_SSL_EMAIL', 'admin@'.$domain);
    $runtime = $value($envoy, "OPS_DEPLOY_{$stageKey}_RUNTIME", 'swoole');

    /*
     * Render bootstrap stubs locally so we can scp the rendered output to the
     * VPS. Pure str_replace — keeps stubs framework-free and reviewable.
     */
    $renderStub = function (string $stubPath, array $vars): string {
        if (! is_file($stubPath)) {
            throw new RuntimeException("Bootstrap stub not found: {$stubPath}");
        }

        $content = file_get_contents($stubPath);
        foreach ($vars as $key => $value) {
            $content = str_replace('{' . '{ ' . $key . ' }' . '}', (string) $value, $content);
        }

        return $content;
    };

    $stubVars = [
        'group' => $group,
        'domain' => $domain,
        'root' => $deployRoot,
        'run_user' => $runUser,
        'php_bin' => $phpBin,
        'octane_port' => $octanePort,
        'reverb_port' => $reverbPort,
        'runtime' => $runtime,
        'ssl_email' => $sslEmail,
    ];

    $renderedNginxConf = is_file($nginxStub) ? $renderStub($nginxStub, $stubVars) : '';
    $renderedSupervisorConf = is_file($supervisorStub) ? $renderStub($supervisorStub, $stubVars) : '';

    $localTmp = sys_get_temp_dir().'/accelerator-bootstrap-'.bin2hex(random_bytes(4));
    $localNginx = $localTmp.'-nginx.conf';
    $localSupervisor = $localTmp.'-supervisor.conf';
    if ($renderedNginxConf !== '') {
        @mkdir(dirname($localNginx), 0700, true);
        file_put_contents($localNginx, $renderedNginxConf);
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

{{-- ════════════════════════════════════════════════════════════════════
     Layout & tooling
     ──────────────────────────────────────────────────────────────────── --}}

@task('prepare-layout', ['on' => 'vps'])
    set -euo pipefail
    mkdir -p {{ $deployRoot }} {{ $releasesPath }} {{ $sharedPath }} {{ $archivePath }} {{ $sharedPath }}/storage/app/public {{ $sharedPath }}/storage/framework {{ $sharedPath }}/storage/logs
    test -d {{ $deployRoot }}
    test -d {{ $releasesPath }}
    test -d {{ $sharedPath }}
    test -d {{ $archivePath }}
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
    composer validate --no-check-all --strict --ansi
    composer install --no-dev --no-scripts --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --quiet --ansi
    {{ $npmBin }} install --frozen-lockfile --no-scripts --quiet
    {{ $npmBin }} run build
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
    @if($service === 'all')
        sudo supervisorctl restart {{ $group }}:*
        sleep 2
        # Fail-fast: kalau ada program yang FATAL setelah restart, batalkan deploy.
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
@endtask

@task('health-check', ['on' => 'vps'])
    set -euo pipefail
    # Curl directly to Octane (bypass nginx) so we verify app boot, not proxy cache.
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 -H "Host: {{ $domain }}" http://127.0.0.1:{{ $octanePort }}/up || echo "000")
    if [ "$status" != "200" ]; then
        echo "[health-check] /up returned HTTP $status from 127.0.0.1:{{ $octanePort }} (expected 200)."
        echo "[health-check] App did NOT boot cleanly. Maintenance mode is still ON if this was deploy."
        exit 1
    fi
    echo "[health-check] OK: 127.0.0.1:{{ $octanePort }}/up returned 200."
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
    echo "Current: $(readlink -f {{ $currentPath }} 2>/dev/null || true)"
    echo "Shared .env: $(test -f {{ $sharedPath }}/.env && echo present || echo missing)"
    sudo supervisorctl status {{ $group }}:*
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
     One-shot VPS bootstrap: nginx vhost + supervisor conf
     ──────────────────────────────────────────────────────────────────── --}}

@task('bootstrap-nginx', ['on' => 'localhost'])
    set -euo pipefail
    test -s {{ $localNginx }}
    echo "[bootstrap-nginx] uploading rendered vhost to {{ $sshHost }}…"
    scp {{ $localNginx }} {{ $sshHost }}:/tmp/{{ $domain }}.conf
    ssh {{ $sshHost }} 'set -euo pipefail
        sudo mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
        # Archive existing config (steering: archive-replaced-before-overwrite)
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
        echo "[bootstrap-nginx] HTTP-only vhost live for {{ $domain }}. Run certbot for SSL:"
        echo "  sudo certbot --nginx -d {{ $domain }} -m {{ $sslEmail }} --agree-tos --redirect"
    '
    rm -f {{ $localNginx }}
@endtask

@task('bootstrap-supervisor', ['on' => 'localhost'])
    set -euo pipefail
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
@endtask
