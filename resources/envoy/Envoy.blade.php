{{--
  Accelerator Envoy release deployer
  ----------------------------------

  This file is intentionally shell-first. It does not bootstrap Laravel and it
  does not call `php artisan ops:*`. Deployment config is read from `.env.envoy`
  in the project root using a tiny local PHP parser.
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
    $bunBin = $value($envoy, "OPS_DEPLOY_{$stageKey}_BUN_BIN", $value($envoy, 'OPS_DEPLOY_BUN_BIN', 'bun'));
    $runUser = $value($envoy, "OPS_DEPLOY_{$stageKey}_RUN_USER", $value($envoy, 'OPS_DEPLOY_RUN_USER', 'www-data'));
    $sshHost = $value($envoy, "OPS_DEPLOY_{$stageKey}_SSH_HOST", $value($envoy, 'OPS_DEPLOY_SSH_HOST', 'onidel'));
    $service = isset($service) ? $service : 'all';
    $seedEnvFile = $root.'/'.($stage === 'prod' ? '.env.production' : '.env.staging');

    foreach (['domain' => $domain, 'root' => $deployRoot, 'repo' => $repo, 'group' => $group] as $name => $required) {
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
@endsetup

@story('init')
    prepare-layout
    deploy
@endstory

@story('deploy')
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
@endstory

@story('deploy-slim')
    ensure-deploy-tools
    sync-env
    clone-release
    link-shared
    harden-release
    prepare-laravel
    switch-current
    invalidate-opcache
    restart-service
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
@endstory

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
    command -v {{ $bunBin }} >/dev/null
    command -v larahelp >/dev/null
    command -v setfacl >/dev/null
    test -f {{ $sharedPath }}/.env
@endtask

@task('clone-release', ['on' => 'vps'])
    set -euo pipefail
    mkdir -p {{ $releasesPath }} {{ $archivePath }}
    if [ -e {{ $releasePath }} ]; then
        echo "Release already exists: {{ $releasePath }}"
        exit 1
    fi
    git clone --branch {{ $branch }} --single-branch {{ $repo }} {{ $releasePath }}
    cd {{ $releasePath }}
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
    composer validate --no-check-all --strict --ansi
    composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --quiet --ansi
    {{ $bunBin }} install --frozen-lockfile --no-scripts --quiet
    {{ $bunBin }} run build
@endtask

@task('harden-release', ['on' => 'vps'])
    set -euo pipefail
    cd {{ $releasePath }}
    find . -type d -not -path "./storage*" -not -path "./bootstrap/cache*" -exec chmod 755 {} +
    find . -type f -not -path "./storage*" -not -path "./bootstrap/cache*" -not -name "artisan" -exec chmod 644 {} +
    chmod 755 artisan
    chmod 600 {{ $sharedPath }}/.env
    sudo setfacl -m u:{{ $runUser }}:r {{ $sharedPath }}/.env
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
    @else
        sudo supervisorctl restart {{ $group }}:{{ $group }}_{{ $service }}
    @endif
@endtask

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

@task('rollback-release', ['on' => 'vps', 'confirm' => true])
    set -euo pipefail
    current="$(readlink -f {{ $currentPath }} 2>/dev/null || true)"
    previous="$(find {{ $releasesPath }} -mindepth 1 -maxdepth 1 -type d | sort | grep -v "^${current}$" | tail -n 1)"
    if [ -z "$previous" ]; then
        echo "No previous release found."
        exit 1
    fi
    if [ -e {{ $rollbackSymlink }} ] || [ -L {{ $rollbackSymlink }} ]; then
        mv {{ $rollbackSymlink }} {{ $archivePath }}/current.rollback.$(date +%Y-%m-%d_%H-%M-%S)
    fi
    ln -s "$previous" {{ $rollbackSymlink }}
    if [ -e {{ $currentPath }} ] || [ -L {{ $currentPath }} ]; then
        mv {{ $currentPath }} {{ $archivePath }}/current.before-rollback-$(date +%Y-%m-%d_%H-%M-%S)
    fi
    mv {{ $rollbackSymlink }} {{ $currentPath }}
    echo "Rolled back to $previous"
@endtask
