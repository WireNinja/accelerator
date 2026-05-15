<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:deploy {--stage= : Deployment stage} {--skip-build : Skip frontend build}')]
#[Description('Deploy the application using release directories and the current symlink')]
final class DeployCommand extends Command
{
    public function handle(): int
    {
        try {
            $stage = DeployConfig::stage($this->option('stage'));
        } catch (\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $errors = DeployConfig::validate($stage);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $paths = $stage['paths'];
        $sha = $this->remoteSha($stage);
        $releaseId = DeployConfig::releaseId($sha);
        $releasePath = "{$paths['releases']}/{$releaseId}";

        $this->components->info("Deploying [{$stage['name']}] release [{$releaseId}]...");

        $this->ensureBaseDirectories($paths);

        if (! is_file("{$paths['shared']}/.env")) {
            $this->components->error("Missing shared env file [{$paths['shared']}/.env]. Run ops:init-env or create it manually.");

            return self::FAILURE;
        }

        $this->runProcess(['git', 'clone', '--branch', (string) $stage['branch'], '--single-branch', (string) $stage['repo'], $releasePath]);
        $this->runProcess(['git', 'reset', '--hard', $sha], $releasePath);

        $this->linkSharedFiles($stage, $releasePath);
        $this->installDependencies($stage, $releasePath);

        if (! $this->option('skip-build')) {
            $this->buildAssets($stage, $releasePath);
        }

        $this->prepareLaravel($stage, $releasePath);
        $this->switchCurrent($paths, $releasePath);
        $this->syncInfrastructure($stage);
        $this->invalidateOpcache($stage, $releasePath);
        $this->restartServices($stage);

        $this->components->info("Deployment [{$releaseId}] completed.");

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function remoteSha(array $stage): string
    {
        $process = new Process(['git', 'ls-remote', (string) $stage['repo'], (string) $stage['branch']]);
        $process->mustRun();
        $output = trim($process->getOutput());

        return strtok($output, "\t ") ?: throw new \RuntimeException('Unable to resolve remote git SHA.');
    }

    /**
     * @param array<string, string> $paths
     */
    private function ensureBaseDirectories(array $paths): void
    {
        foreach (['root', 'releases', 'shared', 'archive'] as $key) {
            if (! is_dir($paths[$key])) {
                mkdir($paths[$key], 0755, true);
            }
        }

        if (! is_dir("{$paths['shared']}/storage")) {
            mkdir("{$paths['shared']}/storage", 0755, true);
        }
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function linkSharedFiles(array $stage, string $releasePath): void
    {
        $shared = $stage['paths']['shared'];

        $this->replaceWithSymlink("{$releasePath}/.env", "{$shared}/.env");
        $this->replaceWithSymlink("{$releasePath}/storage", "{$shared}/storage");

        if (! is_dir("{$shared}/storage/app/public")) {
            mkdir("{$shared}/storage/app/public", 0755, true);
        }

        if (is_dir("{$releasePath}/public")) {
            $this->replaceWithSymlink("{$releasePath}/public/storage", "{$shared}/storage/app/public");
        }
    }

    private function replaceWithSymlink(string $link, string $target): void
    {
        if ($this->requiresSudo($link)) {
            if (file_exists($link) || is_link($link)) {
                $this->runProcess(['sudo', 'mv', $link, $link.'.old_'.now()->format('Y-m-d_H-i-s')]);
            }

            $this->runProcess(['sudo', 'ln', '-s', $target, $link]);

            return;
        }

        if (file_exists($link) || is_link($link)) {
            rename($link, $link.'.old_'.now()->format('Y-m-d_H-i-s'));
        }

        symlink($target, $link);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function installDependencies(array $stage, string $releasePath): void
    {
        $this->runProcess([
            'composer',
            'install',
            '--no-dev',
            '--optimize-autoloader',
            '--classmap-authoritative',
            '--no-interaction',
            '--no-progress',
        ], $releasePath);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function buildAssets(array $stage, string $releasePath): void
    {
        $bun = (string) $stage['bun_bin'];

        $this->runProcess([$bun, 'install', '--frozen-lockfile', '--no-scripts'], $releasePath);
        $this->runProcess([$bun, 'run', 'build'], $releasePath);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function prepareLaravel(array $stage, string $releasePath): void
    {
        $php = (string) $stage['php_bin'];

        $this->runProcess([$php, 'artisan', 'optimize'], $releasePath);
        $this->runProcess([$php, 'artisan', 'migrate', '--force', '--no-interaction'], $releasePath);
    }

    /**
     * @param array<string, string> $paths
     */
    private function switchCurrent(array $paths, string $releasePath): void
    {
        $next = "{$paths['root']}/current.next";

        if (file_exists($next) || is_link($next)) {
            rename($next, "{$paths['archive']}/current.next_".now()->format('Y-m-d_H-i-s'));
        }

        symlink($releasePath, $next);

        if (file_exists($paths['current']) || is_link($paths['current'])) {
            rename($paths['current'], "{$paths['archive']}/current_".now()->format('Y-m-d_H-i-s'));
        }

        rename($next, $paths['current']);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function invalidateOpcache(array $stage, string $releasePath): void
    {
        $code = '$root = $argv[1]; if (! function_exists("opcache_invalidate")) { exit(0); } $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)); foreach ($iterator as $file) { if ($file->isFile() && $file->getExtension() === "php") { opcache_invalidate($file->getPathname(), true); } }';

        $this->runProcess([(string) $stage['php_bin'], '-r', $code, $releasePath]);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function restartServices(array $stage): void
    {
        $this->runProcess(['sudo', 'supervisorctl', 'reread']);
        $this->runProcess(['sudo', 'supervisorctl', 'update']);
        $this->runProcess(['sudo', 'supervisorctl', 'restart', "{$stage['group']}:*"]);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function syncInfrastructure(array $stage): void
    {
        $this->ensureLogDirectory($stage);
        $this->writeSupervisorConfig($stage);
        $this->writeNginxConfig($stage, ssl: $this->hasSslCertificate($stage));

        if (($stage['ssl']['enabled'] ?? false) && ! $this->hasSslCertificate($stage)) {
            $this->writeNginxConfig($stage, ssl: false);
            $this->runProcess(['sudo', 'nginx', '-t']);
            $this->runProcess(['sudo', 'systemctl', 'reload', 'nginx']);
            $this->requestCertificate($stage);
            $this->writeNginxConfig($stage, ssl: true);
        }

        $this->runProcess(['sudo', 'nginx', '-t']);
        $this->runProcess(['sudo', 'systemctl', 'reload', 'nginx']);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function ensureLogDirectory(array $stage): void
    {
        $path = "{$stage['paths']['shared']}/storage/logs";

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function writeSupervisorConfig(array $stage): void
    {
        $programs = [];
        $blocks = [];

        foreach ($stage['services'] as $service) {
            $program = DeployConfig::programName($stage, $service);
            $programs[] = $program;
            $blocks[] = $this->supervisorProgram($stage, $service, $program);
        }

        $content = implode("\n\n", $blocks)."\n\n[group:{$stage['group']}]\nprograms=".implode(',', $programs)."\n";
        $target = "/etc/supervisor/conf.d/{$stage['group']}.conf";

        $this->writeFile($target, $content, $stage);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function supervisorProgram(array $stage, string $service, string $program): string
    {
        $php = (string) $stage['php_bin'];
        $root = "{$stage['paths']['current']}";
        $logs = "{$stage['paths']['shared']}/storage/logs/{$service}.log";
        $user = (string) $stage['run_user'];

        $command = match ($service) {
            'octane' => "{$php} {$root}/artisan octane:start --server=swoole --host=127.0.0.1 --port={$stage['ports']['octane']} --workers=1 --task-workers=1 --max-requests=1000 --no-interaction",
            'horizon' => "{$php} {$root}/artisan horizon --no-interaction",
            'reverb' => "{$php} {$root}/artisan reverb:start --host=127.0.0.1 --port={$stage['ports']['reverb']} --no-interaction",
            'scheduler' => "{$php} {$root}/artisan schedule:work --no-interaction",
            'nightwatch' => "{$php} {$root}/artisan nightwatch:agent --listen-on={$stage['services_raw']['nightwatch']['host']}:{$stage['ports']['nightwatch']} --no-interaction",
            default => throw new \InvalidArgumentException("Unsupported service [{$service}]."),
        };

        return <<<CONF
[program:{$program}]
process_name=%(program_name)s
command={$command}
directory={$root}
autostart=true
autorestart=true
user={$user}
redirect_stderr=true
stdout_logfile={$logs}
startsecs=5
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
CONF;
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function writeNginxConfig(array $stage, bool $ssl): void
    {
        $domain = (string) $stage['domain'];
        $target = "/etc/nginx/sites-available/{$domain}.conf";
        $enabled = "/etc/nginx/sites-enabled/{$domain}.conf";
        $content = $ssl ? $this->nginxSslConfig($stage) : $this->nginxHttpConfig($stage);

        $this->writeFile($target, $content, $stage);
        $this->replaceWithSymlink($enabled, $target);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function nginxHttpConfig(array $stage): string
    {
        $domain = (string) $stage['domain'];
        $root = "{$stage['paths']['current']}/public";
        $handler = $this->nginxHandler($stage);

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain};
    root {$root};

{$handler}

    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;
}
NGINX;
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function nginxSslConfig(array $stage): string
    {
        $domain = (string) $stage['domain'];
        $root = "{$stage['paths']['current']}/public";
        $handler = $this->nginxHandler($stage);

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name {$domain};
    root {$root};

    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

{$handler}

    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;
}
NGINX;
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function nginxHandler(array $stage): string
    {
        $reverb = in_array('reverb', $stage['services'], true) ? <<<NGINX
    location ~ ^/(app|apps|pusher)/ {
        proxy_pass http://127.0.0.1:{$stage['ports']['reverb']};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Port \$server_port;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
    }

NGINX : '';

        if (($stage['runtime'] ?? 'swoole') === 'fpm') {
            return $reverb.<<<NGINX
    index index.php;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ { fastcgi_pass unix:/var/run/php/php8.5-fpm.sock; fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name; include fastcgi_params; }
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot|map|txt)$ { expires 365d; add_header Cache-Control "public, immutable"; }
NGINX;
        }

        return $reverb.<<<NGINX
    location = /index.php { try_files /not_exists @octane; }
    location = /build/sw.js { rewrite ^ /sw.js break; expires 0; add_header Cache-Control "no-cache"; default_type application/javascript; try_files \$uri @octane; }
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot|map|txt)$ { try_files \$uri @octane; expires 365d; add_header Cache-Control "public, immutable"; }
    location / { try_files \$uri @octane; }
    location @octane {
        set \$suffix "";
        if (\$uri = /index.php) { set \$suffix ?\$query_string; }
        proxy_pass http://127.0.0.1:{$stage['ports']['octane']}\$suffix;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "";
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Port \$server_port;
    }
NGINX;
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function hasSslCertificate(array $stage): bool
    {
        return is_dir('/etc/letsencrypt/live/'.$stage['domain']);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function requestCertificate(array $stage): void
    {
        $this->runProcess([
            'sudo',
            'certbot',
            'certonly',
            '--webroot',
            '-w',
            "{$stage['paths']['current']}/public",
            '-d',
            (string) $stage['domain'],
            '--non-interactive',
            '--agree-tos',
            '-m',
            (string) $stage['ssl']['email'],
        ]);
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function writeFile(string $target, string $content, array $stage): void
    {
        if ($this->requiresSudo($target)) {
            $temporary = tempnam(sys_get_temp_dir(), 'accelerator-deploy-');

            if ($temporary === false) {
                throw new RuntimeException('Unable to create temporary deployment file.');
            }

            file_put_contents($temporary, $content);

            if (file_exists($target)) {
                $archive = "{$stage['paths']['archive']}/".basename($target).'.'.now()->format('Y-m-d_H-i-s');
                $this->runProcess(['sudo', 'cp', $target, $archive]);
            }

            $this->runProcess(['sudo', 'cp', $temporary, $target]);

            return;
        }

        if (file_exists($target)) {
            $archive = "{$stage['paths']['archive']}/".basename($target).'.'.now()->format('Y-m-d_H-i-s');
            copy($target, $archive);
        }

        file_put_contents($target, $content);
    }

    private function requiresSudo(string $path): bool
    {
        return str_starts_with($path, '/etc/');
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command, ?string $cwd = null): void
    {
        $this->line('$ '.implode(' ', array_map(fn (string $part): string => str_contains($part, ' ') ? escapeshellarg($part) : $part, $command)));

        $process = new Process($command, $cwd);
        $process->setTimeout(null);
        $process->mustRun(fn (string $type, string $buffer) => $this->output->write($buffer));
    }
}
