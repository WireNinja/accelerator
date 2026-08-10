<?php

declare(strict_types=1);

namespace Deployer;

use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use WireNinja\Accelerator\Configuration\EnvironmentStore;
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
set('http_user', $config->runUser);
set('composer_options', '--prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader --classmap-authoritative');
set('update_code_strategy', 'clone');
set('old_root', '');
option('service', null, InputOption::VALUE_REQUIRED, 'Configured stage service', 'all');
option('backup-mode', null, InputOption::VALUE_REQUIRED, 'all, database, or files', 'all');
option('backup-action', null, InputOption::VALUE_REQUIRED, 'Accelerator backup runtime action', 'list');
option('backup-id', null, InputOption::VALUE_REQUIRED, 'Exact Accelerator backup ID', '');
option('backup-disk', null, InputOption::VALUE_REQUIRED, 'Exact configured backup disk', '');
option('lines', null, InputOption::VALUE_REQUIRED, 'Log lines to read', '200');
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
    $temporary = '{{deploy_path}}/shared/.env.accelerator-upload';
    upload($config->runtimeEnvironmentFile(), $temporary);
    run('chmod 0600 '.$temporary.' && mv -f '.$temporary.' {{deploy_path}}/shared/.env');
});

task('accelerator:runtime-acl', function () use ($config): void {
    $storage = escapeshellarg($config->sharedPath().'/storage');
    $runtimeUser = escapeshellarg($config->runUser);

    run("command sudo -n mkdir -p {$storage}");
    run("command sudo -n setfacl -R -m u:{$runtimeUser}:rwx,m::rwx {$storage}");
    run("command sudo -n find {$storage} -type d -exec setfacl -m d:u:{$runtimeUser}:rwx,d:m::rwx {} +");
});

task('accelerator:environment-push', function () use ($config): void {
    invoke('accelerator:preflight');
    invoke('accelerator:environment');
    invoke('accelerator:runtime-acl');
    run('command sudo -n -u '.escapeshellarg($config->runUser).' '.escapeshellarg($config->phpBinary).' '.escapeshellarg($config->currentPath().'/artisan').' optimize:clear --no-interaction');
    invoke('accelerator:services');
    invoke('accelerator:health');
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

task('accelerator:backup', function () use ($config): void {
    $arguments = ['artisan', 'accelerator:backup:runtime', 'create', '--only=database', '--no-interaction'];

    if (test('[ -f '.$config->currentPath().'/REVISION ]')) {
        $revision = trim(run('cat '.escapeshellarg($config->currentPath().'/REVISION')));

        if (preg_match('/^[a-f0-9]{40,64}$/i', $revision) !== 1) {
            throw new RuntimeException('Active release has an invalid revision marker; pre-migration backup aborted.');
        }

        $arguments[] = "--revision={$revision}";
    }

    run('cd {{release_path}} && command sudo -n -u '.escapeshellarg($config->runUser)
        .' '.escapeshellarg($config->phpBinary)
        .' '.implode(' ', array_map(escapeshellarg(...), $arguments)), forceOutput: true);
});

task('accelerator:backup-runtime', function () use ($config): void {
    $action = (string) input()->getOption('backup-action');
    $mode = (string) input()->getOption('backup-mode');
    $backupId = (string) input()->getOption('backup-id');
    $disk = (string) input()->getOption('backup-disk');

    if (! in_array($action, ['create', 'list', 'status', 'verify', 'cleanup', 'prepare', 'discard', 'post-restore-health', 'notify-test', 'restore-started', 'restore-succeeded', 'restore-failed'], true)) {
        throw new RuntimeException('backup-action is invalid.');
    }

    if (! in_array($mode, ['all', 'database', 'files'], true)) {
        throw new RuntimeException('backup-mode must be all, database, or files.');
    }

    if (in_array($action, ['create', 'cleanup'], true)) {
        invoke('accelerator:runtime-acl');
    }

    $arguments = [
        'artisan',
        'accelerator:backup:runtime',
        $action,
        "--only={$mode}",
        '--no-interaction',
    ];

    if ($backupId !== '') {
        $arguments[] = "--backup={$backupId}";
    }

    if ($disk !== '') {
        $arguments[] = "--disk={$disk}";
    }

    $command = implode(' ', array_map(escapeshellarg(...), $arguments));
    run('cd '.escapeshellarg($config->currentPath()).' && command sudo -n -u '.escapeshellarg($config->runUser).' '.escapeshellarg($config->phpBinary).' '.$command.' || true', forceOutput: true);
});

task('accelerator:backup-restore', function () use ($config): void {
    $mode = (string) input()->getOption('backup-mode');
    $backupId = (string) input()->getOption('backup-id');
    $disk = (string) input()->getOption('backup-disk');

    if (! in_array($mode, ['all', 'database', 'files'], true)) {
        throw new RuntimeException('backup-mode must be all, database, or files.');
    }

    if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $backupId) !== 1) {
        throw new RuntimeException('A safe exact backup ID is required for restore.');
    }

    $environment = (new EnvironmentStore($config->projectRoot))->read(
        '.accelerator/environments/'.$config->stage.'.env',
    );
    $driver = $environment['DB_CONNECTION'] ?? 'sqlite';
    $database = $environment['DB_DATABASE'] ?? '';
    $username = $environment['DB_USERNAME'] ?? '';
    $host = $environment['DB_HOST'] ?? '';
    $socket = $environment['DB_SOCKET'] ?? '';
    $maintenanceSecret = getenv('ACCELERATOR_RESTORE_SECRET');

    if (! is_string($maintenanceSecret) || preg_match('/^[a-f0-9]{48}$/', $maintenanceSecret) !== 1) {
        throw new RuntimeException('A valid local maintenance bypass receipt is required for restore.');
    }
    $requiredCommands = in_array($mode, ['all', 'files'], true) ? ['rsync'] : [];

    if (in_array($mode, ['all', 'database'], true)) {
        $requiredCommands[] = match ($driver) {
            'sqlite' => 'sqlite3',
            'mysql', 'mariadb' => 'mysql',
            'pgsql' => 'psql',
            default => throw new RuntimeException("Unsupported restore database driver [{$driver}]."),
        };
    }

    foreach (array_unique($requiredCommands) as $requiredCommand) {
        if (trim(run('command sudo -n sh -c '.escapeshellarg('command -v '.escapeshellarg($requiredCommand).' >/dev/null 2>&1').' && echo yes || echo no')) !== 'yes') {
            throw new RuntimeException("Required restore command [{$requiredCommand}] is unavailable through passwordless sudo.");
        }
    }
    $decode = static function (string $output): array {
        if (preg_match('/ACCELERATOR_BACKUP_RESULT=([A-Za-z0-9+\/=]+)/', $output, $matches) !== 1) {
            throw new RuntimeException('Backup runtime returned no machine-readable result.');
        }

        $json = base64_decode($matches[1], true);
        $result = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($result) || ($result['status'] ?? 'ERROR') !== 'OK') {
            throw new RuntimeException(is_array($result) && is_string($result['error'] ?? null)
                ? $result['error']
                : 'Backup runtime operation failed.');
        }

        return $result;
    };
    $runtime = static function (string $action, string $mode, string $backupId = '', string $disk = '') use ($config): string {
        $arguments = ['artisan', 'accelerator:backup:runtime', $action, "--only={$mode}", '--no-interaction'];

        if ($backupId !== '') {
            $arguments[] = "--backup={$backupId}";
        }

        if ($disk !== '') {
            $arguments[] = "--disk={$disk}";
        }

        return 'cd '.escapeshellarg($config->currentPath())
            .' && command sudo -n -u '.escapeshellarg($config->runUser)
            .' '.escapeshellarg($config->phpBinary)
            .' '.implode(' ', array_map(escapeshellarg(...), $arguments));
    };
    $maintenance = false;
    $phase = 'preflight';
    $restoreServices = array_values(array_filter(
        $config->supervisorServices(),
        static fn (string $service): bool => $service !== 'nightowl',
    ));

    invoke('accelerator:preflight');
    invoke('deploy:lock');

    try {
        $phase = 'archive verification';
        invoke('accelerator:runtime-acl');
        $prepared = $decode(run($runtime('prepare', $mode, $backupId, $disk), forceOutput: true));
        $databaseDump = is_string($prepared['database_dump'] ?? null) ? $prepared['database_dump'] : '';
        $filesPath = is_string($prepared['files_path'] ?? null) ? $prepared['files_path'] : '';
        $expectedPreparationRoot = $config->sharedPath().'/storage/framework/accelerator-restore/'.$backupId;
        $canonicalize = static fn (string $path): string => trim(run(
            'command sudo -n -u '.escapeshellarg($config->runUser).' readlink -f -- '.escapeshellarg($path),
        ));
        $canonicalPreparationRoot = $canonicalize($expectedPreparationRoot);

        if ($canonicalPreparationRoot === '') {
            throw new RuntimeException('Unable to resolve the stage-owned restore preparation directory.');
        }

        foreach (array_filter([$databaseDump, $filesPath]) as $preparedPath) {
            $canonicalPreparedPath = $canonicalize($preparedPath);

            if ($canonicalPreparedPath === '' || ! str_starts_with($canonicalPreparedPath, $canonicalPreparationRoot.'/')) {
                throw new RuntimeException('Restore preparation returned a path outside the stage-owned temporary directory.');
            }
        }

        $phase = 'emergency backup';
        $emergency = $decode(run($runtime('create', 'all'), forceOutput: true));
        $phase = 'maintenance and process isolation';
        run($runtime('restore-started', $mode, $backupId).' || true', forceOutput: true);
        run('cd '.escapeshellarg($config->currentPath())
            .' && command sudo -n -u '.escapeshellarg($config->runUser)
            .' '.escapeshellarg($config->phpBinary).' artisan down --secret='.escapeshellarg($maintenanceSecret).' --no-interaction');
        $maintenance = true;

        foreach (['scheduler', 'horizon', 'queue', 'reverb', 'octane'] as $service) {
            if (in_array($service, $restoreServices, true)) {
                run('command sudo -n supervisorctl stop '.escapeshellarg($config->group.':'.$config->programName($service)));
            }
        }

        if (in_array($mode, ['all', 'database'], true)) {
            $phase = 'database restore';
            if ($databaseDump === '') {
                throw new RuntimeException('Prepared restore contains no database dump.');
            }

            if ($driver === 'sqlite') {
                $expected = $config->sharedPath().'/database/database.sqlite';

                if ($database !== $expected) {
                    throw new RuntimeException("SQLite restore target must be [{$expected}].");
                }

                $temporaryDatabase = $expected.'.accelerator-restore';
                run('command rm -f '.escapeshellarg($temporaryDatabase)
                    .' && command sqlite3 '.escapeshellarg($temporaryDatabase).' < '.escapeshellarg($databaseDump)
                    .' && command sqlite3 '.escapeshellarg($temporaryDatabase).' "PRAGMA integrity_check;" | grep -Fxq ok'
                    .' && command chmod 0660 '.escapeshellarg($temporaryDatabase)
                    .' && command mv -f '.escapeshellarg($temporaryDatabase).' '.escapeshellarg($expected));
            } else {
                foreach (['database' => $database, 'username' => $username] as $label => $value) {
                    if (preg_match('/^[a-z0-9_]+$/i', $value) !== 1) {
                        throw new RuntimeException("Deployment {$label} must contain only letters, numbers, and underscores.");
                    }
                }

                if ($socket === '' && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
                    throw new RuntimeException('Automatic restore is limited to a database on the deployment host.');
                }

                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    $grantee = "'".$username."'@%";
                    $grantCount = trim(run('command sudo -n mysql --batch --skip-column-names --execute='.escapeshellarg(
                        'SELECT COUNT(*) FROM information_schema.SCHEMA_PRIVILEGES WHERE TABLE_SCHEMA = '.$sqlString($database).' AND GRANTEE LIKE '.$sqlString($grantee).';',
                    )));

                    if (! ctype_digit($grantCount) || (int) $grantCount < 1) {
                        throw new RuntimeException("MySQL database [{$database}] is not proven to belong to account [{$username}].");
                    }

                    $identifier = '`'.str_replace('`', '``', $database).'`';
                    run('command sudo -n mysql --execute='.escapeshellarg("DROP DATABASE IF EXISTS {$identifier}; CREATE DATABASE {$identifier} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"));
                    run('command sudo -n sh -c '.escapeshellarg('mysql '.escapeshellarg($database).' < '.escapeshellarg($databaseDump)));
                } elseif ($driver === 'pgsql') {
                    $sqlString = static fn (string $value): string => "'".str_replace("'", "''", $value)."'";
                    $roleExists = trim(run('command sudo -n -u postgres psql --tuples-only --no-align --command='.escapeshellarg(
                        'SELECT 1 FROM pg_roles WHERE rolname = '.$sqlString($username).';',
                    )));

                    if ($roleExists !== '1') {
                        throw new RuntimeException("PostgreSQL role [{$username}] does not exist.");
                    }

                    $owner = trim(run('command sudo -n -u postgres psql --tuples-only --no-align --command='.escapeshellarg(
                        'SELECT pg_catalog.pg_get_userbyid(datdba) FROM pg_database WHERE datname = '.$sqlString($database).';',
                    )));

                    if ($owner !== $username) {
                        throw new RuntimeException("PostgreSQL database [{$database}] is not owned by [{$username}].");
                    }

                    run('command sudo -n -u postgres psql --set=ON_ERROR_STOP=1 --command='.escapeshellarg(
                        'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '.$sqlString($database).' AND pid <> pg_backend_pid();',
                    ));
                    run('command sudo -n -u postgres dropdb --if-exists '.escapeshellarg($database));
                    run('command sudo -n -u postgres createdb --owner='.escapeshellarg($username).' '.escapeshellarg($database));
                    $remoteDump = '/tmp/'.$config->group.'-'.$backupId.'.sql';
                    run('command sudo -n install -m 0600 -o postgres -g postgres '.escapeshellarg($databaseDump).' '.escapeshellarg($remoteDump));
                    run('trap '.escapeshellarg('sudo -n rm -f '.$remoteDump).' EXIT; command sudo -n -u postgres psql --set=ON_ERROR_STOP=1 --dbname='.escapeshellarg($database).' --file='.escapeshellarg($remoteDump));
                } else {
                    throw new RuntimeException("Unsupported restore database driver [{$driver}].");
                }
            }
        }

        if (in_array($mode, ['all', 'files'], true)) {
            $phase = 'mutable file restore';
            if ($filesPath === '') {
                throw new RuntimeException('Prepared restore contains no storage/app tree.');
            }

            $backupName = (string) ($environment['ACCELERATOR_BACKUP_NAME'] ?? '');

            if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $backupName) !== 1) {
                throw new RuntimeException('ACCELERATOR_BACKUP_NAME is invalid for file restoration.');
            }

            run('command sudo -n rsync -a --delete '
                .'--exclude='.escapeshellarg('/private/'.$backupName.'/')
                .' --exclude='.escapeshellarg('/'.$backupName.'/')
                .' '.escapeshellarg(rtrim($filesPath, '/').'/')
                .' '.escapeshellarg($config->sharedPath().'/storage/app/'));
            invoke('accelerator:runtime-acl');
        }

        $phase = 'cache and service recovery';
        run('cd '.escapeshellarg($config->currentPath())
            .' && command sudo -n -u '.escapeshellarg($config->runUser)
            .' '.escapeshellarg($config->phpBinary).' artisan optimize:clear --no-interaction'
            .' && command sudo -n -u '.escapeshellarg($config->runUser)
            .' '.escapeshellarg($config->phpBinary).' artisan optimize --no-interaction'
            .' && command sudo -n -u '.escapeshellarg($config->runUser)
            .' '.escapeshellarg($config->phpBinary).' artisan up --no-interaction');
        $maintenance = false;
        foreach ($restoreServices as $service) {
            run('command sudo -n supervisorctl restart '.escapeshellarg($config->group.':'.$config->programName($service)));
        }

        if ($config->httpRuntime === 'fpm') {
            run('command sudo -n systemctl reload '.$config->fpmService);
        }
        $phase = 'post-restore health';
        invoke('accelerator:health');
        $decode(run($runtime('post-restore-health', $mode), forceOutput: true));

        if ($config->hasSupervisorPrograms()) {
            run('command sudo -n supervisorctl status '.escapeshellarg($config->group.':*')." | awk '\$2 != \"RUNNING\" { failed=1 } END { exit failed }'");
        }

        if ($config->nightowlEnabled) {
            run('curl --fail --silent --show-error --max-time 10 http://127.0.0.1:'.$config->nightowlHealthPort.'/status >/dev/null');
        }

        run($runtime('restore-succeeded', $mode, $backupId).' || true', forceOutput: true);
        run($runtime('discard', $mode, $backupId).' || true', forceOutput: true);
        writeln('Emergency pre-restore backup: '.($emergency['backup_id'] ?? 'unknown'));
    } catch (Throwable $exception) {
        run($runtime('restore-failed', $mode, $backupId).' || true', forceOutput: true);

        throw new RuntimeException(
            "Restore failed during {$phase}".($maintenance ? ' while the stage remains in maintenance mode with its application writer processes stopped.' : ' before maintenance mode was enabled.').' '.$exception->getMessage(),
            previous: $exception,
        );
    } finally {
        invoke('deploy:unlock');
    }
});

foreach (['status', 'start', 'stop', 'restart'] as $operation) {
    task("accelerator:service-{$operation}", function () use ($config, $operation): void {
        if (! $config->hasSupervisorPrograms()) {
            throw new RuntimeException("No Supervisor services are enabled for {$config->stage}.");
        }

        $service = (string) input()->getOption('service');
        $target = $service === 'all'
            ? $config->group.':*'
            : $config->group.':'.$config->programName($service);
        $command = 'command sudo -n supervisorctl '.$operation.' '.escapeshellarg($target);

        if ($operation === 'status') {
            $command .= ' || true';
        }

        run($command, forceOutput: true);
    });
}

task('accelerator:revision', function (): void {
    run("if [ -f {{current_path}}/ACCELERATOR_SUCCESSFUL_RELEASE ]; then revision=\$(cat {{current_path}}/REVISION 2>/dev/null || true); else revision=''; fi; printf 'ACCELERATOR_REVISION revision=%s\\n' \"\$revision\"", forceOutput: true);
});

task('accelerator:logs', function () use ($config): void {
    $service = (string) input()->getOption('service');
    $allowed = ['laravel', ...$config->supervisorServices()];

    if (! in_array($service, $allowed, true)) {
        throw new RuntimeException('Unknown or disabled log service ['.$service.'].');
    }

    $lines = filter_var(input()->getOption('lines'), FILTER_VALIDATE_INT);

    if (! is_int($lines) || $lines < 1 || $lines > 5000) {
        throw new RuntimeException('lines must be between 1 and 5000.');
    }

    $file = $config->sharedPath().'/storage/logs/'.($service === 'laravel' ? 'laravel.log' : "{$service}.log");
    run('command sudo -n tail -n '.$lines.' '.escapeshellarg($file), forceOutput: true);
});

task('accelerator:database-init', function () use ($config): void {
    $environment = (new EnvironmentStore($config->projectRoot))->read(
        '.accelerator/environments/'.$config->stage.'.env',
    );
    $driver = $environment['DB_CONNECTION'] ?? 'sqlite';
    $database = $environment['DB_DATABASE'] ?? '';

    if ($driver === 'sqlite') {
        $expected = $config->sharedPath().'/database/database.sqlite';

        if ($database !== $expected) {
            throw new RuntimeException("SQLite database for {$config->stage} must be [{$expected}].");
        }

        run('mkdir -p '.escapeshellarg(dirname($expected)).' && touch '.escapeshellarg($expected).' && chmod 0660 '.escapeshellarg($expected));

        return;
    }

    $username = $environment['DB_USERNAME'] ?? '';
    $host = $environment['DB_HOST'] ?? '';
    $socket = $environment['DB_SOCKET'] ?? '';

    foreach (['database' => $database, 'username' => $username] as $label => $value) {
        if (preg_match('/^[a-z0-9_]+$/i', $value) !== 1) {
            throw new RuntimeException("Deployment {$label} must contain only letters, numbers, and underscores.");
        }
    }

    if ($socket === '' && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('deploy:init can bootstrap only a database on the deployment host. Create the external database explicitly first.');
    }

    $sqlString = static fn (string $value): string => "'".str_replace("'", "''", $value)."'";

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
        $available = trim(run("command sudo -n sh -c 'command -v mysql >/dev/null 2>&1' && echo yes || echo no"));

        if ($available !== 'yes') {
            throw new RuntimeException('MySQL client is unavailable through passwordless sudo on the deployment host.');
        }

        $hosts = preg_split('/\R/', trim(run(
            'command sudo -n mysql --batch --skip-column-names -e '.escapeshellarg(
                'SELECT Host FROM mysql.user WHERE User = '.$sqlString($username).' ORDER BY Host;',
            ),
        ))) ?: [];
        $hosts = array_values(array_filter($hosts, static fn (string $accountHost): bool => $accountHost !== ''));

        if ($hosts === []) {
            throw new RuntimeException("MySQL account [{$username}] must exist before deploy:init can grant the stage database.");
        }

        $identifier = '`'.str_replace('`', '``', $database).'`';
        $sql = "CREATE DATABASE IF NOT EXISTS {$identifier} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";

        foreach ($hosts as $accountHost) {
            $sql .= "GRANT ALL PRIVILEGES ON {$identifier}.* TO ".$sqlString($username).'@'.$sqlString($accountHost).";\n";
        }

        $temporary = sys_get_temp_dir().'/accelerator-database-'.bin2hex(random_bytes(8)).'.sql';
        $remote = '/tmp/'.$config->group.'-database-init.sql';

        try {
            file_put_contents($temporary, $sql, LOCK_EX);
            chmod($temporary, 0600);
            upload($temporary, $remote);
            run('chmod 0600 '.escapeshellarg($remote).' && trap '.escapeshellarg('rm -f '.$remote).' EXIT; command sudo -n mysql < '.escapeshellarg($remote));
        } finally {
            @unlink($temporary);
        }

        return;
    }

    if ($driver === 'pgsql') {
        $roleExists = trim(run(
            'command sudo -n -u postgres psql --tuples-only --no-align --command='.escapeshellarg(
                'SELECT 1 FROM pg_roles WHERE rolname = '.$sqlString($username).';',
            ),
        ));

        if ($roleExists !== '1') {
            throw new RuntimeException("PostgreSQL role [{$username}] must exist before deploy:init can create the stage database.");
        }

        $databaseExists = trim(run(
            'command sudo -n -u postgres psql --tuples-only --no-align --command='.escapeshellarg(
                'SELECT 1 FROM pg_database WHERE datname = '.$sqlString($database).';',
            ),
        ));

        if ($databaseExists !== '1') {
            run('command sudo -n -u postgres createdb --owner='.escapeshellarg($username).' '.escapeshellarg($database));
        }

        return;
    }

    throw new RuntimeException("Unsupported deployment database driver [{$driver}].");
});

task('accelerator:nightowl-database-init', function () use ($config): void {
    if (! $config->nightowlEnabled) {
        return;
    }

    $environment = (new EnvironmentStore($config->projectRoot))->read(
        '.accelerator/environments/'.$config->stage.'.env',
    );
    $database = $config->nightowlDatabaseName();
    $username = $environment['NIGHTOWL_DB_USERNAME'] ?? '';
    $password = $environment['NIGHTOWL_DB_PASSWORD'] ?? '';

    if ($username !== $database || $password === '') {
        throw new RuntimeException('NightOwl PostgreSQL identity is invalid. Re-run accelerator:configure environment for this stage.');
    }

    $identifier = '"'.str_replace('"', '""', $database).'"';
    $literal = "'".str_replace("'", "''", $password)."'";
    $sql = "DO \$\$\nBEGIN\n    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '{$database}') THEN\n        CREATE ROLE {$identifier} LOGIN;\n    END IF;\nEND\n\$\$;\nALTER ROLE {$identifier} WITH LOGIN PASSWORD {$literal};\n";
    $temporary = sys_get_temp_dir().'/accelerator-nightowl-'.bin2hex(random_bytes(8)).'.sql';
    $remote = '/tmp/'.$config->group.'-nightowl-init.sql';

    try {
        file_put_contents($temporary, $sql, LOCK_EX);
        chmod($temporary, 0600);
        upload($temporary, $remote);
        run(
            'chmod 0600 '.escapeshellarg($remote)
            .' && command sudo -n chown postgres:postgres '.escapeshellarg($remote)
            .' && trap '.escapeshellarg('sudo -n rm -f '.$remote).' EXIT; command sudo -n -u postgres psql --set=ON_ERROR_STOP=1 --file='.escapeshellarg($remote),
        );
    } finally {
        @unlink($temporary);
    }

    $databaseExists = trim(run(
        'command sudo -n -u postgres psql --tuples-only --no-align --command='.escapeshellarg(
            "SELECT 1 FROM pg_database WHERE datname = '{$database}';",
        ),
    ));

    if ($databaseExists !== '1') {
        run('command sudo -n -u postgres createdb --owner='.escapeshellarg($username).' '.escapeshellarg($database));
    } else {
        run('command sudo -n -u postgres psql --set=ON_ERROR_STOP=1 --command='.escapeshellarg("ALTER DATABASE {$identifier} OWNER TO {$identifier};"));
    }
});

task('accelerator:nightowl-schema', function () use ($config): void {
    if (! $config->nightowlEnabled) {
        return;
    }

    $marker = $config->sharedPath().'/.nightowl-installed';
    $command = test('[ -f '.escapeshellarg($marker).' ]') ? 'nightowl:migrate' : 'nightowl:install';

    cd('{{release_path}}');
    run('{{bin/php}} artisan '.$command.' --no-interaction', forceOutput: true);
    run('touch '.escapeshellarg($marker));
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
    $legacyIdentity = 'stage='.$config->stage.' domain='.$config->domain.' root='.$config->deployRoot;

    $requiredCommands = ['git', $config->phpBinary, 'composer', 'sudo', 'getfacl', 'setfacl'];
    $requiredSudoCommands = ['ss', 'nginx', 'certbot'];

    if ($config->hasSupervisorPrograms()) {
        $requiredSudoCommands[] = 'supervisorctl';
    }

    if ($config->nightowlEnabled) {
        $requiredSudoCommands[] = 'psql';
        $requiredSudoCommands[] = 'createdb';
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
        .'elif [ -f '.escapeshellarg($rootOwner).' ] && grep -Fq '.escapeshellarg('WireNinja-Accelerator kind=root').' '.escapeshellarg($rootOwner).' && grep -Fq '.escapeshellarg($legacyIdentity).' '.escapeshellarg($rootOwner).'; then echo legacy-owned; '
        .'elif [ -z "$(find '.escapeshellarg($config->deployRoot).' -mindepth 1 -maxdepth 1 -print -quit)" ]; then echo empty; '
        .'else echo collision:unmanaged; fi',
    ));

    if (str_starts_with($rootState, 'collision:')) {
        throw new RuntimeException("Deployment preflight failed: root [{$config->deployRoot}] is {$rootState} and is not owned by {$config->deploymentKey}:{$config->stage}.");
    }

    $nginxOwner = trim(run(
        'if [ ! -e '.escapeshellarg($expectedNginx).' ]; then echo absent; '
        .'elif sudo -n grep -Fxq '.escapeshellarg($nginxMarker).' '.escapeshellarg($expectedNginx).'; then echo owned; '
        .'elif sudo -n grep -Fq '.escapeshellarg('WireNinja-Accelerator kind=nginx').' '.escapeshellarg($expectedNginx).' && sudo -n grep -Fq '.escapeshellarg($legacyIdentity).' '.escapeshellarg($expectedNginx).'; then echo legacy-owned; '
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
    $legacySupervisor = '';

    if ($config->hasSupervisorPrograms()) {
        $supervisorOwner = trim(run(
            'if [ ! -e '.escapeshellarg($expectedSupervisor).' ]; then echo absent; '
            .'elif sudo -n grep -Fxq '.escapeshellarg($supervisorMarker).' '.escapeshellarg($expectedSupervisor).'; then echo owned; '
            .'else echo collision:unmanaged; fi',
        ));

        if ($supervisorOwner === 'collision:unmanaged') {
            throw new RuntimeException("Deployment preflight failed: Supervisor file [{$expectedSupervisor}] exists without the expected Accelerator ownership marker.");
        }

        if ($supervisorOwner === 'absent') {
            $legacySupervisor = trim(run(
                'command sudo -n grep -RslF '.escapeshellarg($legacyIdentity).' /etc/supervisor/conf.d 2>/dev/null || true',
            ));
            $legacyFiles = array_values(array_filter(preg_split('/\R/', $legacySupervisor) ?: []));

            if (count($legacyFiles) > 1) {
                throw new RuntimeException('Deployment preflight failed: multiple legacy Supervisor files claim this stage.');
            }

            if ($legacyFiles !== []) {
                $legacySupervisor = $legacyFiles[0];
                $supervisorOwner = 'legacy-owned';
            }
        }

        $groupPattern = '^\\[(group:'.preg_quote($config->group, '/').'|program:'.preg_quote($config->group, '/').'-).*\\]';
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

        $programService = str_starts_with($service, 'nightowl') ? 'nightowl' : $service;
        $program = $config->programName($programService);
        $status = trim(run('command sudo -n supervisorctl status '.escapeshellarg($config->group.':'.$program).' 2>/dev/null || true'));

        if ($programService === 'nightowl' && $supervisorOwner === 'owned') {
            $oldProgram = $config->group.'-nightwatch';
            $oldStatus = trim(run('command sudo -n supervisorctl status '.escapeshellarg($config->group.':'.$oldProgram).' 2>/dev/null || true'));

            if (str_contains($oldStatus, 'RUNNING')) {
                $portStates[] = "{$service}:{$port}=migration-owned";

                continue;
            }

            $oldNightwatch = trim(run(
                'command sudo -n grep -Fq '.escapeshellarg('[program:'.$config->group.'-nightwatch]').' '.escapeshellarg($expectedSupervisor)
                .' && command sudo -n grep -Eq '.escapeshellarg('(--listen-on=|:)(?:127\.0\.0\.1:)?'.$port.'([^0-9]|$)').' '.escapeshellarg($expectedSupervisor)
                .' && echo yes || echo no',
            )) === 'yes';

            if ($oldNightwatch) {
                $portStates[] = "{$service}:{$port}=migration-owned";

                continue;
            }
        }

        if ($supervisorOwner === 'legacy-owned') {
            $legacyOwnsPort = trim(run('command sudo -n grep -Eq '.escapeshellarg('(--port=|:)(?:127\\.0\\.0\\.1:)?'.$port.'([^0-9]|$)').' '.escapeshellarg($legacySupervisor).' && echo yes || echo no')) === 'yes';

            if ($legacyOwnsPort) {
                $portStates[] = "{$service}:{$port}=legacy-owned";

                continue;
            }
        }

        if ($supervisorOwner !== 'owned' || ! str_contains($status, 'RUNNING')) {
            throw new RuntimeException("Deployment preflight failed: {$service} port [{$port}] is already in use by a process not owned by Supervisor program [{$program}].");
        }

        $portStates[] = "{$service}:{$port}=owned";
    }

    run("printf 'ACCELERATOR_PREFLIGHT deployment_key={$config->deploymentKey}\\nACCELERATOR_PREFLIGHT stage={$config->stage}\\nACCELERATOR_PREFLIGHT host={$config->sshHost}\\nACCELERATOR_PREFLIGHT domain={$config->domain}\\nACCELERATOR_PREFLIGHT root={$config->deployRoot}\\nACCELERATOR_PREFLIGHT supervisor_group={$config->group}\\nACCELERATOR_PREFLIGHT root_state={$rootState}\\nACCELERATOR_PREFLIGHT nginx_state={$nginxOwner}\\nACCELERATOR_PREFLIGHT supervisor_state={$supervisorOwner}\\nACCELERATOR_PREFLIGHT ports=".implode(',', $portStates)."\\n'", forceOutput: true);
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
    invoke('accelerator:runtime-acl');
    run('printf %s '.escapeshellarg($config->ownerToken('root')).' > '.escapeshellarg($config->deployRoot.'/.accelerator-owner'));
    upload($temporary.'/nginx.conf', '/tmp/'.$config->group.'-nginx.conf');
    upload($temporary.'/nginx-secure.conf', '/tmp/'.$config->group.'-nginx-secure.conf');
    run('command sudo -n install -m 0644 /tmp/'.$config->group.'-nginx.conf /etc/nginx/sites-available/'.$config->domain);
    run('command sudo -n ln -sfn /etc/nginx/sites-available/'.$config->domain.' /etc/nginx/sites-enabled/'.$config->domain);

    if ($config->hasSupervisorPrograms()) {
        $legacyIdentity = 'stage='.$config->stage.' domain='.$config->domain.' root='.$config->deployRoot;
        $expectedSupervisor = '/etc/supervisor/conf.d/'.$config->group.'.conf';
        run(
            'legacy=$(command sudo -n grep -RslF '.escapeshellarg($legacyIdentity).' /etc/supervisor/conf.d 2>/dev/null | grep -Fvx '.escapeshellarg($expectedSupervisor).' | head -n 1 || true); '
            .'if [ -n "$legacy" ]; then old_group=$(command sudo -n sed -n "s/^# .* group=\\([^ ]*\\).*$/\\1/p" "$legacy" | head -n 1); '
            .'if [ -n "$old_group" ]; then command sudo -n supervisorctl stop "$old_group:*" >/dev/null 2>&1 || true; fi; '
            .'command sudo -n rm -f "$legacy"; command sudo -n supervisorctl reread; command sudo -n supervisorctl update; fi',
        );
        upload($temporary.'/supervisor.conf', '/tmp/'.$config->group.'-supervisor.conf');
        run('command sudo -n install -m 0644 /tmp/'.$config->group.'-supervisor.conf /etc/supervisor/conf.d/'.$config->group.'.conf');
    }

    run('command sudo -n nginx -t && command sudo -n systemctl reload nginx');
    run('command sudo -n certbot certonly --webroot --webroot-path='.escapeshellarg($config->sharedPath().'/acme').' --non-interactive --agree-tos --keep-until-expiring --email '.escapeshellarg($config->sslEmail).' -d '.escapeshellarg($config->domain));
    run('command sudo -n install -m 0644 /tmp/'.$config->group.'-nginx-secure.conf /etc/nginx/sites-available/'.$config->domain);
    run('command sudo -n nginx -t && command sudo -n systemctl reload nginx');
});

task('accelerator:status', function () use ($config): void {
    run("printf 'ACCELERATOR_STATUS revision=%s\\n' \"\$(cat {{current_path}}/REVISION 2>/dev/null || true)\"", forceOutput: true);
    run("printf 'ACCELERATOR_STATUS deployment_key={$config->deploymentKey}\\nACCELERATOR_STATUS stage={$config->stage}\\nACCELERATOR_STATUS domain={$config->domain}\\nACCELERATOR_STATUS root={$config->deployRoot}\\nACCELERATOR_STATUS supervisor_group={$config->group}\\n'", forceOutput: true);
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
    run('if [ -h {{deploy_path}}/release ]; then failed="$(basename "$(readlink {{deploy_path}}/release)")"; if [ ! -f "{{deploy_path}}/releases/$failed/ACCELERATOR_SUCCESSFUL_RELEASE" ]; then touch "{{deploy_path}}/releases/$failed/BAD_RELEASE"; fi; fi');
});

task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
    'artisan:optimize',
    'artisan:migrate',
    'deploy:publish',
]);

before('deploy:shared', 'accelerator:environment');
after('deploy:update_code', 'accelerator:submodule');
after('deploy:vendors', 'accelerator:frontend');
after('deploy:vendors', 'accelerator:nightowl-schema');
before('artisan:migrate', 'accelerator:backup');
before('accelerator:backup', 'accelerator:runtime-acl');
after('deploy:symlink', 'accelerator:activate-release');
after('rollback', 'accelerator:services');
after('deploy:failed', 'accelerator:mark-failed-release');
after('deploy:failed', 'deploy:unlock');
