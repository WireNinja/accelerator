<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use RuntimeException;

final readonly class DeploymentRenderer
{
    public function __construct(private DeploymentConfig $config) {}

    public function nginx(bool $secure): string
    {
        $stub = $secure ? 'nginx-vhost-ssl.conf.stub' : 'nginx-vhost-http.conf.stub';

        return $this->renderStub($stub, [
            'domain' => $this->config->domain,
            'root' => $this->config->deployRoot,
            'shared' => $this->config->sharedPath(),
            'ssl_email' => $this->config->sslEmail,
            'maintenance_guard' => $this->maintenanceGuard(),
            'reverb_location' => $this->reverbLocation(),
            'application_locations' => $this->applicationLocations(),
        ]);
    }

    public function supervisor(): string
    {
        $programs = [];
        $names = [];

        if ($this->config->httpRuntime === 'octane') {
            $names[] = $this->programName('octane');
            $programs[] = $this->octaneProgram();
        }

        if ($this->config->horizonEnabled) {
            $names[] = $this->programName('horizon');
            $programs[] = $this->program('horizon', "{$this->config->phpBinary} {$this->config->deployRoot}/current/artisan horizon", 3600);
        }

        if ($this->config->queueWorkerEnabled) {
            $names[] = $this->programName('queue');
            $programs[] = $this->queueProgram();
        }

        if ($this->config->reverbEnabled) {
            $names[] = $this->programName('reverb');
            $programs[] = $this->program(
                'reverb',
                "{$this->config->phpBinary} {$this->config->deployRoot}/current/artisan reverb:start --host=127.0.0.1 --port={$this->config->reverbPort}",
            );
        }

        if ($this->config->schedulerEnabled) {
            $names[] = $this->programName('scheduler');
            $programs[] = $this->program('scheduler', "{$this->config->phpBinary} {$this->config->deployRoot}/current/artisan schedule:work");
        }

        if ($this->config->nightwatchEnabled) {
            $names[] = $this->programName('nightwatch');
            $programs[] = $this->program(
                'nightwatch',
                "{$this->config->phpBinary} {$this->config->deployRoot}/current/artisan nightwatch:agent --listen-on=127.0.0.1:{$this->config->nightwatchPort} --server={$this->config->domain} --silent",
            );
        }

        if ($programs === []) {
            return '';
        }

        $programs[] = "[group:{$this->config->group}]\nprograms=".implode(',', $names);

        return implode("\n\n", $programs)."\n";
    }

    public function nginxHash(bool $secure): string
    {
        return hash('sha256', $this->nginx($secure));
    }

    public function supervisorHash(): string
    {
        return hash('sha256', $this->supervisor());
    }

    private function maintenanceGuard(): string
    {
        return <<<NGINX
    # Accelerator owns maintenance at Nginx so FPM, Octane, static files, and
    # websocket upgrades obey the same shared marker before PHP boots.
    error_page 503 = @accelerator_maintenance;
    set \$accelerator_maintenance 0;
    if (-f {$this->config->sharedPath()}/storage/framework/down) { set \$accelerator_maintenance 1; }
    if (\$uri = /up) { set \$accelerator_maintenance 0; }
    if (\$uri ~ ^/\.well-known/acme-challenge/) { set \$accelerator_maintenance 0; }
    if (\$accelerator_maintenance = 1) { return 503; }

    location @accelerator_maintenance {
        default_type text/html;
        add_header Retry-After 60 always;
        add_header Cache-Control "no-store" always;
        return 503 '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance</title><style>body{font:16px system-ui;margin:12vh auto;max-width:42rem;padding:0 1.5rem;color:#18181b}h1{font-size:2rem}p{line-height:1.6;color:#52525b}</style><h1>Maintenance in progress</h1><p>The application is being updated. Please try again shortly.</p></html>';
    }
NGINX;
    }

    private function reverbLocation(): string
    {
        if (! $this->config->reverbEnabled) {
            return '';
        }

        return <<<NGINX
    location ~ ^/(app|apps|pusher)(/|\$) {
        proxy_pass http://127.0.0.1:{$this->config->reverbPort};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 60s;
    }
NGINX;
    }

    private function applicationLocations(): string
    {
        if ($this->config->httpRuntime === 'octane') {
            return <<<NGINX
    location ~* \.(?:css|js|png|jpe?g|gif|ico|svg|webp|woff2?|ttf|eot|map|txt)$ {
        try_files \$uri =404;
        expires 365d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files \$uri @octane;
    }

    location @octane {
        proxy_pass http://127.0.0.1:{$this->config->octanePort};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Port \$server_port;
        proxy_set_header X-Forwarded-Host \$host;
        proxy_set_header Connection "";
        proxy_buffering off;
        proxy_read_timeout 60s;
    }
NGINX;
        }

        return <<<NGINX
    index index.php;

    location ~* \.(?:css|js|png|jpe?g|gif|ico|svg|webp|woff2?|ttf|eot|map|txt)$ {
        try_files \$uri =404;
        expires 365d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files \$uri /index.php?\$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:{$this->config->fpmSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root/index.php;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php\$ {
        return 404;
    }
NGINX;
    }

    private function octaneProgram(): string
    {
        $command = $this->config->octaneServer === 'swoole'
            ? "{$this->config->phpBinary} -d upload_max_filesize=100M -d post_max_size=110M {$this->config->deployRoot}/current/artisan octane:swoole --host=127.0.0.1 --port={$this->config->octanePort} --workers={$this->config->octaneWorkers} --task-workers={$this->config->octaneTaskWorkers} --max-requests=500"
            : "{$this->config->phpBinary} -d upload_max_filesize=100M -d post_max_size=110M {$this->config->deployRoot}/current/artisan octane:start --server={$this->config->octaneServer} --host=127.0.0.1 --port={$this->config->octanePort} --workers={$this->config->octaneWorkers} --max-requests=500";

        return $this->program('octane', $command);
    }

    private function queueProgram(): string
    {
        $program = $this->programName('queue');

        return <<<CONF
[program:{$program}]
process_name=%(program_name)s_%(process_num)02d
command={$this->config->phpBinary} {$this->config->deployRoot}/current/artisan queue:work {$this->config->queueWorkerConnection} --queue={$this->config->queueWorkerQueue} --sleep=3 --tries=3 --timeout=90 --max-time=3600
directory={$this->config->deployRoot}/current
user={$this->config->runUser}
numprocs={$this->config->queueWorkerProcesses}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
stdout_logfile={$this->config->sharedPath()}/storage/logs/queue.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF;
    }

    private function program(string $service, string $command, int $stopWaitSeconds = 60): string
    {
        $program = $this->programName($service);

        return <<<CONF
[program:{$program}]
command={$command}
directory={$this->config->deployRoot}/current
user={$this->config->runUser}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs={$stopWaitSeconds}
stdout_logfile={$this->config->sharedPath()}/storage/logs/{$service}.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
redirect_stderr=true
CONF;
    }

    private function programName(string $service): string
    {
        return $this->config->group.'_'.$service;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function renderStub(string $filename, array $variables): string
    {
        $path = dirname(__DIR__, 2).'/stubs/vps/'.$filename;
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException("Unable to read deployment stub [{$filename}].");
        }

        foreach ($variables as $key => $value) {
            $contents = str_replace('{{ '.$key.' }}', $value, $contents);
        }

        if (preg_match('/{{\s*[A-Za-z0-9_]+\s*}}/', $contents) === 1) {
            throw new RuntimeException("Unresolved placeholder in deployment stub [{$filename}].");
        }

        return rtrim($contents)."\n";
    }
}
