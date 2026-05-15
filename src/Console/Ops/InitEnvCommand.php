<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:init-env {--stage= : Deployment stage} {--force : Archive existing shared .env and create a new one}')]
#[Description('Initialize shared deployment .env for a stage')]
final class InitEnvCommand extends Command
{
    public function handle(): int
    {
        try {
            $stage = DeployConfig::stage($this->option('stage'));
        } catch (\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $target = "{$stage['paths']['shared']}/.env";

        if (is_file($target) && ! $this->option('force')) {
            $this->components->warn("Shared env already exists at [{$target}].");

            return self::SUCCESS;
        }

        if (! is_dir($stage['paths']['shared'])) {
            mkdir($stage['paths']['shared'], 0755, true);
        }

        if (is_file($target)) {
            rename($target, "{$stage['paths']['archive']}/.env.".now()->format('Y-m-d_H-i-s'));
        }

        $source = base_path('.env.example');

        if (! is_file($source)) {
            $source = __DIR__.'/../../../.base-env.example';
        }

        $content = file_get_contents($source);

        if ($content === false) {
            $this->components->error('Unable to read env template.');

            return self::FAILURE;
        }

        $content = $this->setEnv($content, 'APP_ENV', $stage['name'] === 'prod' ? 'production' : 'staging');
        $content = $this->setEnv($content, 'APP_DEBUG', 'false');
        $content = $this->setEnv($content, 'APP_URL', 'https://'.$stage['domain']);
        $content = $this->setEnv($content, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        $content = $this->setEnv($content, 'REVERB_APP_ID', (string) random_int(100000, 999999));
        $content = $this->setEnv($content, 'REVERB_APP_KEY', Str::random(20));
        $content = $this->setEnv($content, 'REVERB_APP_SECRET', Str::random(32));

        file_put_contents($target, $content);
        chmod($target, 0600);

        $this->components->info("Shared env initialized at [{$target}]. Review secrets before deploy.");

        return self::SUCCESS;
    }

    private function setEnv(string $content, string $key, string $value): string
    {
        $line = $key.'='.$value;

        if (preg_match('/^'.$key.'=.*$/m', $content)) {
            return preg_replace('/^'.$key.'=.*$/m', $line, $content) ?? $content;
        }

        return rtrim($content).PHP_EOL.$line.PHP_EOL;
    }
}
