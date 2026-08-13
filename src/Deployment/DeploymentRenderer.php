<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

final readonly class DeploymentRenderer
{
    public function __construct(private DeploymentConfig $config) {}

    public function nginx(bool $secure): string
    {
        $application = <<<NGINX
    index index.php;

    location ~ ^/livewire-[a-f0-9]+/ {
        try_files \$uri /index.php?\$query_string;
    }

    location ~* \.(?:css|js|png|jpe?g|gif|ico|svg|webp|woff2?|ttf|eot|map|txt)\$ {
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

    location ~ \.php\$ { return 404; }
NGINX;
        $maintenance = <<<NGINX
    error_page 503 = @accelerator_maintenance;
    set \$accelerator_maintenance 0;
    if (-f {$this->config->sharedPath()}/storage/framework/down) { set \$accelerator_maintenance 1; }
    if (\$uri = {$this->config->healthPath}) { set \$accelerator_maintenance 0; }
    if (\$uri ~ ^/\.well-known/acme-challenge/) { set \$accelerator_maintenance 0; }
    if (\$accelerator_maintenance = 1) { return 503; }

    location @accelerator_maintenance {
        add_header Retry-After 60 always;
        return 503 'Maintenance in progress';
    }
NGINX;
        $server = <<<NGINX
# {$this->config->ownerToken('nginx')}
server {
    listen 80;
    listen [::]:80;
    server_name {$this->config->domain};
    root {$this->config->deployRoot}/current/public;
    access_log /var/log/nginx/{$this->config->domain}.access.log;
    error_log /var/log/nginx/{$this->config->domain}.error.log;
    client_max_body_size 110m;
    server_tokens off;

{$maintenance}
    location ^~ /.well-known/acme-challenge/ {
        root {$this->config->sharedPath()}/acme;
        try_files \$uri =404;
    }

    location ~ /\.(?!well-known(?:/|\$)) { deny all; }
{$application}
}
NGINX;

        if (! $secure) {
            return $server."\n";
        }

        $redirect = <<<NGINX
# {$this->config->ownerToken('nginx')}
server {
    listen 80;
    listen [::]:80;
    server_name {$this->config->domain};
    location ^~ /.well-known/acme-challenge/ { root {$this->config->sharedPath()}/acme; try_files \$uri =404; }
    location / { return 301 https://\$host\$request_uri; }
}
NGINX;
        $secureServer = str_replace(
            ["    listen 80;\n    listen [::]:80;", "    server_name {$this->config->domain};"],
            ["    listen 443 ssl;\n    listen [::]:443 ssl;\n    http2 on;", "    server_name {$this->config->domain};\n    ssl_certificate /etc/letsencrypt/live/{$this->config->domain}/fullchain.pem;\n    ssl_certificate_key /etc/letsencrypt/live/{$this->config->domain}/privkey.pem;"],
            $server,
        );

        return $redirect."\n\n".$secureServer."\n";
    }

    public function cron(): string
    {
        return <<<CRON
# {$this->config->ownerToken('cron')}
* * * * * {$this->config->runUser} {$this->config->phpBinary} {$this->config->deployRoot}/current/artisan schedule:run --no-interaction >> {$this->config->sharedPath()}/storage/logs/scheduler.log 2>&1
CRON.PHP_EOL;
    }

    public function nginxHash(bool $secure): string
    {
        return hash('sha256', $this->nginx($secure));
    }

    public function cronHash(): string
    {
        return hash('sha256', $this->cron());
    }
}
