<?php

declare(strict_types=1);

namespace Deployer;

use RuntimeException;
use Throwable;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentRenderer;

require 'recipe/laravel.php';

$projectRoot = getenv('ACCELERATOR_PROJECT_ROOT') ?: getcwd();
$stage = getenv('ACCELERATOR_DEPLOY_STAGE') ?: null;
$config = DeploymentConfig::load($projectRoot, is_string($stage) ? $stage : null);
$renderer = new DeploymentRenderer($config);
$nodeEnvironment = 'export NVM_DIR="$HOME/.nvm"; if [ -s "$NVM_DIR/nvm.sh" ]; then . "$NVM_DIR/nvm.sh"; fi';

host($config->stage)
    ->setHostname($config->sshHost)
    ->set('repository', $config->repository)
    ->set('branch', $config->branch)
    ->set('deploy_path', $config->deployRoot);

set('keep_releases', $config->keepReleases);
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);
set('writable_dirs', ['bootstrap/cache', 'storage']);
set('writable_mode', 'acl');
set('writable_recursive', true);
set('writable_acl_force', true);
set('http_user', $config->runUser);
set('composer_options', '--prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader --classmap-authoritative');
set('update_code_strategy', 'clone');
set('old_root', '');
set('rollback_candidate', function (): string {
    $currentRelease = basename(run('readlink {{current_path}}'));
    $foundCurrent = false;

    foreach (get('releases_list') as $release) {
        if ($release === $currentRelease) {
            $foundCurrent = true;

            continue;
        }

        if (! $foundCurrent) {
            continue;
        }

        if (test("[ -f {{deploy_path}}/releases/{$release}/ACCELERATOR_SUCCESSFUL_RELEASE ]")
            && ! test("[ -f {{deploy_path}}/releases/{$release}/BAD_RELEASE ]")) {
            return $release;
        }
    }

    throw new RuntimeException('No previously successful release is available for rollback.');
});

task('accelerator:environment', function () use ($config): void {
    run('mkdir -p {{deploy_path}}/shared');
    upload($config->runtimeEnvironmentFile(), '{{deploy_path}}/shared/.env');
});

task('accelerator:submodule', function (): void {
    cd('{{release_path}}');

    $environment = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_SSH_COMMAND' => get('git_ssh_command'),
    ];

    run('{{bin/git}} submodule sync -- packages/accelerator', env: $environment);
    run('{{bin/git}} submodule update --init --force --depth 1 -- packages/accelerator', env: $environment);
});

task('accelerator:frontend', function () use ($config, $nodeEnvironment): void {
    cd('{{release_path}}');

    if ($config->packageManager === 'pnpm') {
        run($nodeEnvironment.'; pnpm install --frozen-lockfile');
        run($nodeEnvironment.'; pnpm run build');
    } else {
        run($nodeEnvironment.'; npm ci');
        run($nodeEnvironment.'; npm rebuild sharp --ignore-scripts=false');
        run($nodeEnvironment.'; npm run build');
    }
});

task('accelerator:backup', function (): void {
    cd('{{release_path}}');
    run('{{bin/php}} artisan backup:run --only-db --disable-notifications --no-interaction');
});

task('accelerator:services', function () use ($config): void {
    if ($config->hasSupervisorPrograms()) {
        run('command sudo -n supervisorctl restart '.$config->group.':*');
    }

    if ($config->httpRuntime === 'fpm') {
        run('command sudo -n systemctl reload '.$config->fpmService);
    }
});

task('accelerator:supervisor-load', function () use ($config): void {
    if ($config->hasSupervisorPrograms()) {
        run('command sudo -n supervisorctl reread && command sudo -n supervisorctl update');
    }
});

task('accelerator:health', function () use ($config): void {
    run('for attempt in $(seq 1 10); do curl --fail --silent --show-error --max-time 15 https://'.$config->domain.$config->healthPath.' >/dev/null && exit 0; sleep 2; done; exit 1');
});

task('accelerator:health-or-restore', function (): void {
    try {
        invoke('accelerator:health');
    } catch (Throwable $exception) {
        warning('Health check failed after the release switch. Restoring the previous code symlink; database migrations are not reversed.');

        try {
            invoke('rollback');
            invoke('accelerator:services');
        } catch (Throwable $rollbackException) {
            throw new RuntimeException('Health check failed and no previously successful release could be restored; the new code symlink remains active for diagnosis. Database recovery remains manual.', previous: $rollbackException);
        }

        throw new RuntimeException('Health check failed. The previous release symlink and services were restored; review database compatibility manually.', previous: $exception);
    }
});

task('accelerator:preflight', function () use ($config, $nodeEnvironment): void {
    $expectedNginx = '/etc/nginx/sites-available/'.$config->domain;
    $expectedSupervisor = '/etc/supervisor/conf.d/'.$config->group.'.conf';
    $rootOwner = $config->deployRoot.'/.accelerator-owner';
    $rootToken = $config->ownerToken('root');
    $nginxMarker = '# '.$config->ownerToken('nginx');
    $supervisorMarker = '# '.$config->ownerToken('supervisor');

    $requiredCommands = ['git', $config->phpBinary, 'composer', 'sudo', 'getfacl', 'setfacl'];
    $requiredSudoCommands = ['ss', 'nginx', 'certbot'];

    if ($config->hasSupervisorPrograms()) {
        $requiredSudoCommands[] = 'supervisorctl';
    }

    foreach (array_unique($requiredCommands) as $command) {
        $available = trim(run('command -v '.escapeshellarg($command).' >/dev/null 2>&1 && echo yes || echo no'));

        if ($available !== 'yes') {
            throw new RuntimeException("Deployment preflight failed: required command [{$command}] is unavailable on [{$config->sshHost}].");
        }
    }

    foreach (['node', $config->packageManager] as $command) {
        $available = trim(run($nodeEnvironment.'; command -v '.escapeshellarg($command).' >/dev/null 2>&1 && echo yes || echo no'));

        if ($available !== 'yes') {
            throw new RuntimeException("Deployment preflight failed: Node runtime command [{$command}] is unavailable after loading system/NVM environment on [{$config->sshHost}].");
        }
    }

    if (trim(run('command sudo -n true >/dev/null 2>&1 && echo yes || echo no')) !== 'yes') {
        throw new RuntimeException("Deployment preflight failed: passwordless sudo is unavailable on [{$config->sshHost}].");
    }

    foreach ($requiredSudoCommands as $command) {
        $available = trim(run('command sudo -n sh -c '.escapeshellarg('command -v '.escapeshellarg($command).' >/dev/null 2>&1').' && echo yes || echo no'));

        if ($available !== 'yes') {
            throw new RuntimeException("Deployment preflight failed: required sudo command [{$command}] is unavailable on [{$config->sshHost}].");
        }
    }

    $rootState = trim(run(
        'if [ ! -e '.escapeshellarg($config->deployRoot).' ]; then echo absent; '
        .'elif [ ! -d '.escapeshellarg($config->deployRoot).' ]; then echo collision:not-directory; '
        .'elif [ -f '.escapeshellarg($rootOwner).' ] && [ "$(cat '.escapeshellarg($rootOwner).')" = '.escapeshellarg($rootToken).' ]; then echo owned; '
        .'elif [ -z "$(find '.escapeshellarg($config->deployRoot).' -mindepth 1 -maxdepth 1 -print -quit)" ]; then echo empty; '
        .'else echo collision:unmanaged; fi',
    ));

    if (str_starts_with($rootState, 'collision:')) {
        throw new RuntimeException("Deployment preflight failed: root [{$config->deployRoot}] is {$rootState} and is not owned by {$config->project}:{$config->stage}.");
    }

    $nginxOwner = trim(run(
        'if [ ! -e '.escapeshellarg($expectedNginx).' ]; then echo absent; '
        .'elif sudo -n grep -Fxq '.escapeshellarg($nginxMarker).' '.escapeshellarg($expectedNginx).'; then echo owned; '
        .'else echo collision:unmanaged; fi',
    ));

    if ($nginxOwner === 'collision:unmanaged') {
        throw new RuntimeException("Deployment preflight failed: Nginx file [{$expectedNginx}] exists without the expected Accelerator ownership marker.");
    }

    $domainPattern = 'server_name[[:space:]]+'.preg_quote($config->domain, '/').'([[:space:];]|$)';
    $nginxMatches = trim(run(
        'command sudo -n grep -RslE '.escapeshellarg($domainPattern).' /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null || true',
    ));

    foreach (preg_split('/\R/', $nginxMatches) ?: [] as $match) {
        if ($match === '') {
            continue;
        }

        $resolved = trim(run('readlink -f '.escapeshellarg($match).' 2>/dev/null || true'));

        if ($resolved !== $expectedNginx) {
            throw new RuntimeException("Deployment preflight failed: domain [{$config->domain}] is already owned by Nginx config [{$match}].");
        }
    }

    $supervisorOwner = 'disabled';

    if ($config->hasSupervisorPrograms()) {
        $supervisorOwner = trim(run(
            'if [ ! -e '.escapeshellarg($expectedSupervisor).' ]; then echo absent; '
            .'elif sudo -n grep -Fxq '.escapeshellarg($supervisorMarker).' '.escapeshellarg($expectedSupervisor).'; then echo owned; '
            .'else echo collision:unmanaged; fi',
        ));

        if ($supervisorOwner === 'collision:unmanaged') {
            throw new RuntimeException("Deployment preflight failed: Supervisor file [{$expectedSupervisor}] exists without the expected Accelerator ownership marker.");
        }

        $groupPattern = '^\\[(group:'.preg_quote($config->group, '/').'|program:'.preg_quote($config->group, '/').'_).*\\]';
        $supervisorMatches = trim(run(
            'command sudo -n grep -RslE '.escapeshellarg($groupPattern).' /etc/supervisor/conf.d 2>/dev/null || true',
        ));

        foreach (preg_split('/\R/', $supervisorMatches) ?: [] as $match) {
            if ($match !== '' && $match !== $expectedSupervisor) {
                throw new RuntimeException("Deployment preflight failed: service group [{$config->group}] is already declared by Supervisor config [{$match}].");
            }
        }
    }

    $portStates = [];

    foreach ($config->listeningServices() as $service => $port) {
        $occupied = trim(run(
            'command sudo -n ss -H -ltn '.escapeshellarg("sport = :{$port}").' 2>/dev/null | grep -q . && echo yes || echo no',
        )) === 'yes';

        if (! $occupied) {
            $portStates[] = "{$service}:{$port}=free";

            continue;
        }

        $program = $config->group.'_'.$service;
        $status = trim(run('command sudo -n supervisorctl status '.escapeshellarg($config->group.':'.$program).' 2>/dev/null || true'));

        if ($supervisorOwner !== 'owned' || ! str_contains($status, 'RUNNING')) {
            throw new RuntimeException("Deployment preflight failed: {$service} port [{$port}] is already in use by a process not owned by Supervisor program [{$program}].");
        }

        $portStates[] = "{$service}:{$port}=owned";
    }

    run("printf 'ACCELERATOR_PREFLIGHT stage={$config->stage}\\nACCELERATOR_PREFLIGHT host={$config->sshHost}\\nACCELERATOR_PREFLIGHT domain={$config->domain}\\nACCELERATOR_PREFLIGHT root={$config->deployRoot}\\nACCELERATOR_PREFLIGHT service_group={$config->group}\\nACCELERATOR_PREFLIGHT root_state={$rootState}\\nACCELERATOR_PREFLIGHT nginx_state={$nginxOwner}\\nACCELERATOR_PREFLIGHT supervisor_state={$supervisorOwner}\\nACCELERATOR_PREFLIGHT ports=".implode(',', $portStates)."\\n'", forceOutput: true);
});

task('accelerator:activate-release', function (): void {
    invoke('accelerator:supervisor-load');
    invoke('accelerator:services');
    invoke('accelerator:health-or-restore');
    run('touch {{release_path}}/ACCELERATOR_SUCCESSFUL_RELEASE');
});

task('accelerator:provision', function () use ($config, $renderer): void {
    invoke('accelerator:preflight');

    $temporary = sys_get_temp_dir().'/accelerator-deploy-'.bin2hex(random_bytes(8));
    mkdir($temporary, 0700, true);
    file_put_contents($temporary.'/nginx.conf', $renderer->nginx(false));
    file_put_contents($temporary.'/nginx-secure.conf', $renderer->nginx(true));
    file_put_contents($temporary.'/supervisor.conf', $renderer->supervisor());

    run('mkdir -p '.$config->deployRoot.'/shared/database '.$config->deployRoot.'/shared/acme');
    run('printf %s '.escapeshellarg($config->ownerToken('root')).' > '.escapeshellarg($config->deployRoot.'/.accelerator-owner'));
    upload($temporary.'/nginx.conf', '/tmp/'.$config->group.'-nginx.conf');
    upload($temporary.'/nginx-secure.conf', '/tmp/'.$config->group.'-nginx-secure.conf');
    run('command sudo -n install -m 0644 /tmp/'.$config->group.'-nginx.conf /etc/nginx/sites-available/'.$config->domain);
    run('command sudo -n ln -sfn /etc/nginx/sites-available/'.$config->domain.' /etc/nginx/sites-enabled/'.$config->domain);

    if ($config->hasSupervisorPrograms()) {
        upload($temporary.'/supervisor.conf', '/tmp/'.$config->group.'-supervisor.conf');
        run('command sudo -n install -m 0644 /tmp/'.$config->group.'-supervisor.conf /etc/supervisor/conf.d/'.$config->group.'.conf');
    }

    run('command sudo -n nginx -t && command sudo -n systemctl reload nginx');
    run('command sudo -n certbot certonly --webroot --webroot-path='.escapeshellarg($config->sharedPath().'/acme').' --non-interactive --agree-tos --keep-until-expiring --email '.escapeshellarg($config->sslEmail).' -d '.escapeshellarg($config->domain));
    run('command sudo -n install -m 0644 /tmp/'.$config->group.'-nginx-secure.conf /etc/nginx/sites-available/'.$config->domain);
    run('command sudo -n nginx -t && command sudo -n systemctl reload nginx');
});

task('accelerator:status', function () use ($config): void {
    run("printf 'ACCELERATOR_STATUS stage={$config->stage}\\nACCELERATOR_STATUS domain={$config->domain}\\nACCELERATOR_STATUS root={$config->deployRoot}\\n'", forceOutput: true);
    run("printf 'ACCELERATOR_STATUS current=%s\\n' \"\$(readlink {{deploy_path}}/current 2>/dev/null || true)\"", forceOutput: true);
    run("current=\"\$(readlink -f {{deploy_path}}/current 2>/dev/null || true)\"; previous=''; for marker in \$(find {{deploy_path}}/releases -mindepth 2 -maxdepth 2 -name ACCELERATOR_SUCCESSFUL_RELEASE 2>/dev/null | sort -Vr); do candidate=\"\$(dirname \"\$marker\")\"; if [ \"\$candidate\" != \"\$current\" ] && [ ! -f \"\$candidate/BAD_RELEASE\" ]; then previous=\"\$candidate\"; break; fi; done; printf 'ACCELERATOR_STATUS previous=%s\\n' \"\$previous\"", forceOutput: true);
    run("if test -f {{deploy_path}}/.dep/deploy.lock; then value=yes; else value=no; fi; printf 'ACCELERATOR_STATUS locked=%s\\n' \"\$value\"", forceOutput: true);
    run("if test -f {{deploy_path}}/current/storage/framework/down; then value=yes; else value=no; fi; printf 'ACCELERATOR_STATUS maintenance=%s\\n' \"\$value\"", forceOutput: true);
    run("printf 'ACCELERATOR_STATUS disk_available_kb=%s\\n' \"\$(df -Pk {{deploy_path}} | awk 'NR==2 {print \$4}')\"", forceOutput: true);
    run("printf 'ACCELERATOR_STATUS recent_backup=%s\\n' \"\$(find {{deploy_path}}/shared/storage/app/private -type f ! -name '.*' 2>/dev/null | sort | tail -n 1 || true)\"", forceOutput: true);
    run("printf 'ACCELERATOR_STATUS supervisor=%s\\n' \"\$(sudo -n supervisorctl status {$config->group}:* 2>/dev/null | tr '\\n' ';' || echo unavailable)\"", forceOutput: true);
    run("if curl --fail --silent --max-time 10 https://{$config->domain}{$config->healthPath} >/dev/null; then value=ok; else value=failed; fi; printf 'ACCELERATOR_STATUS health=%s\\n' \"\$value\"", forceOutput: true);
});

task('accelerator:relocate', function () use ($config): void {
    $oldRoot = get('old_root');

    if (! is_string($oldRoot) || ! str_starts_with($oldRoot, '/var/www/') || $oldRoot === $config->deployRoot) {
        throw new RuntimeException('ACCELERATOR_OLD_ROOT must identify a different /var/www root.');
    }

    invoke('deploy:lock');

    try {
        run('command sudo -n mkdir -p '.$config->deployRoot);
        run('command sudo -n rsync -a --numeric-ids '.escapeshellarg(rtrim($oldRoot, '/').'/').' '.escapeshellarg($config->deployRoot.'/'));
        invoke('accelerator:provision');
        invoke('accelerator:supervisor-load');
        invoke('accelerator:services');
        invoke('accelerator:health');
        writeln('Old root preserved at '.$oldRoot.'. Remove it manually only after verifying the new domain.');
    } finally {
        invoke('deploy:unlock');
    }
});

task('accelerator:mark-failed-release', function (): void {
    run('if [ -h {{deploy_path}}/release ]; then failed="$(basename "$(readlink {{deploy_path}}/release)")"; touch "{{deploy_path}}/releases/$failed/BAD_RELEASE"; fi');
});

before('deploy:shared', 'accelerator:environment');
after('deploy:update_code', 'accelerator:submodule');
after('deploy:vendors', 'accelerator:frontend');
before('artisan:migrate', 'accelerator:backup');
after('deploy:symlink', 'accelerator:activate-release');
after('rollback', 'accelerator:services');
after('deploy:failed', 'accelerator:mark-failed-release');
after('deploy:failed', 'deploy:unlock');
