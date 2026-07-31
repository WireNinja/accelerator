<?php

declare(strict_types=1);

namespace Deployer;

use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentRenderer;

require 'recipe/laravel.php';

$projectRoot = getenv('ACCELERATOR_PROJECT_ROOT') ?: getcwd();
$stage = getenv('ACCELERATOR_DEPLOY_STAGE') ?: null;
$config = DeploymentConfig::load($projectRoot, is_string($stage) ? $stage : null);
$renderer = new DeploymentRenderer($config);

host($config->stage)
    ->setHostname($config->sshHost)
    ->set('repository', $config->repository)
    ->set('branch', $config->branch)
    ->set('deploy_path', $config->deployRoot);

set('keep_releases', $config->keepReleases);
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);
set('writable_dirs', ['bootstrap/cache', 'storage']);
set('writable_mode', 'chmod');
set('writable_chmod_mode', '0775');
set('composer_options', '--prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader --classmap-authoritative');
set('old_root', '');

task('accelerator:environment', function () use ($config): void {
    run('mkdir -p {{deploy_path}}/shared');
    upload($config->runtimeEnvironmentFile(), '{{deploy_path}}/shared/.env');
});

task('accelerator:frontend', function () use ($config): void {
    cd('{{release_path}}');

    if ($config->packageManager === 'pnpm') {
        run('pnpm install --frozen-lockfile');
        run('pnpm run build');
    } else {
        run('npm ci');
        run('npm rebuild sharp --ignore-scripts=false');
        run('npm run build');
    }
});

task('accelerator:backup', function (): void {
    cd('{{release_path}}');
    run('{{bin/php}} artisan backup:run --only-db --disable-notifications --no-interaction');
});

task('accelerator:services', function () use ($config): void {
    if ($config->hasSupervisorPrograms()) {
        run('sudo -n supervisorctl restart '.$config->group.':*');
    }

    if ($config->httpRuntime === 'fpm') {
        run('sudo -n systemctl reload '.$config->fpmService);
    }
});

task('accelerator:health', function () use ($config): void {
    run('curl --fail --silent --show-error --max-time 15 https://'.$config->domain.$config->healthPath.' >/dev/null');
});

task('accelerator:provision', function () use ($config, $renderer): void {
    $temporary = sys_get_temp_dir().'/accelerator-deploy-'.bin2hex(random_bytes(8));
    mkdir($temporary, 0700, true);
    file_put_contents($temporary.'/nginx.conf', $renderer->nginx(false));
    file_put_contents($temporary.'/supervisor.conf', $renderer->supervisor());

    run('mkdir -p '.$config->deployRoot.'/shared/storage '.$config->deployRoot.'/shared/database '.$config->deployRoot.'/shared/acme');
    upload($temporary.'/nginx.conf', '/tmp/'.$config->group.'-nginx.conf');
    run('sudo -n install -m 0644 /tmp/'.$config->group.'-nginx.conf /etc/nginx/sites-available/'.$config->domain);
    run('sudo -n ln -sfn /etc/nginx/sites-available/'.$config->domain.' /etc/nginx/sites-enabled/'.$config->domain);

    if ($config->hasSupervisorPrograms()) {
        upload($temporary.'/supervisor.conf', '/tmp/'.$config->group.'-supervisor.conf');
        run('sudo -n install -m 0644 /tmp/'.$config->group.'-supervisor.conf /etc/supervisor/conf.d/'.$config->group.'.conf');
        run('sudo -n supervisorctl reread && sudo -n supervisorctl update');
    }

    run('sudo -n nginx -t && sudo -n systemctl reload nginx');
    run('sudo -n certbot --nginx --non-interactive --agree-tos --redirect --email '.$config->sslEmail.' -d '.$config->domain);
});

task('accelerator:status', function () use ($config): void {
    run("printf 'stage={$config->stage}\\ndomain={$config->domain}\\nroot={$config->deployRoot}\\ncurrent='; readlink {{deploy_path}}/current || true; printf 'locked='; test -f {{deploy_path}}/.dep/deploy.lock && echo yes || echo no; df -h {{deploy_path}} | tail -1");
});

task('accelerator:relocate', function () use ($config): void {
    $oldRoot = get('old_root');

    if (! is_string($oldRoot) || ! str_starts_with($oldRoot, '/var/www/') || $oldRoot === $config->deployRoot) {
        throw new \RuntimeException('ACCELERATOR_OLD_ROOT must identify a different /var/www root.');
    }

    run('sudo -n mkdir -p '.$config->deployRoot);
    run('sudo -n rsync -a --numeric-ids '.escapeshellarg(rtrim($oldRoot, '/').'/').' '.escapeshellarg($config->deployRoot.'/'));
    writeln('Old root preserved at '.$oldRoot.'. Remove it manually only after verifying the new domain.');
});

before('deploy:shared', 'accelerator:environment');
after('deploy:update_code', 'accelerator:frontend');
before('artisan:migrate', 'accelerator:backup');
after('deploy:symlink', 'accelerator:services');
after('accelerator:services', 'accelerator:health');
after('rollback', 'accelerator:services');
after('deploy:failed', 'deploy:unlock');
