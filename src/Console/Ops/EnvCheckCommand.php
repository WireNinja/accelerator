<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:env-check {--stage= : Deployment stage}')]
#[Description('Validate deployment environment and stage configuration')]
final class EnvCheckCommand extends Command
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
        $envPath = "{$stage['paths']['shared']}/.env";

        if (! is_file($envPath)) {
            $errors[] = "Missing shared env file [{$envPath}].";
        }

        $env = is_file($envPath) ? $this->readEnv($envPath) : [];

        if (($env['APP_KEY'] ?? '') === '') {
            $errors[] = 'APP_KEY is missing in shared .env.';
        }

        if ($stage['name'] === 'prod') {
            if (($env['APP_ENV'] ?? null) !== 'production') {
                $errors[] = 'APP_ENV must be [production] for prod stage.';
            }

            if (DeployConfig::truthy($env['APP_DEBUG'] ?? false)) {
                $errors[] = 'APP_DEBUG must be false for prod stage.';
            }
        }

        if (($env['APP_URL'] ?? '') === '' || str_contains((string) ($env['APP_URL'] ?? ''), 'localhost')) {
            $errors[] = 'APP_URL is missing or still points to localhost.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info("Deployment environment for stage [{$stage['name']}] is valid.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function readEnv(string $path): array
    {
        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, "\"'");
        }

        return $values;
    }
}
