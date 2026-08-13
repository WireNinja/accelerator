<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Agent;

use Composer\InstalledVersions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use WireNinja\Accelerator\AcceleratorServiceProvider;
use WireNinja\Accelerator\Contracts\AcceleratorUser;

#[Signature('accelerator:doctor {--json : Output as JSON} {--compact : Compact JSON output} {--section= : runtime, database, frontend, or security}')]
#[Description('Verify the Accelerator installation contract and report actionable failures')]
final class DoctorCommand extends Command
{
    /**
     * @var list<array{category: string, label: string, value: string, status: 'ok'|'warning'|'error', message: string|null}>
     */
    private array $checks = [];

    /** @throws JsonException */
    public function handle(): int
    {
        $section = $this->option('section');

        if (! is_string($section) || $section === '') {
            foreach (['runtime', 'database', 'frontend', 'security'] as $selected) {
                $this->inspectSection($selected);
            }
        } elseif (in_array($section, ['runtime', 'database', 'frontend', 'security'], true)) {
            $this->inspectSection($section);
        } else {
            $this->record('Runtime', 'Section', $section, 'error', 'Section must be runtime, database, frontend, or security.');
        }

        $errors = $this->messagesFor('error');
        $warnings = $this->messagesFor('warning');

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'status' => $errors !== [] ? 'ERROR' : ($warnings !== [] ? 'WARNING' : 'OK'),
                'errors' => $errors,
                'warnings' => $warnings,
                'checks' => $this->checks,
            ], ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($this->groupedChecks() as $category => $checks) {
            $this->components->info($category);
            $this->table(['Check', 'Current', 'Status'], array_map(fn (array $check): array => [
                $check['label'], $check['value'], strtoupper($check['status']),
            ], $checks));
        }

        if ($errors !== []) {
            $this->components->error('Installation contract failed.');
            $this->components->bulletList($errors);
        } elseif ($warnings !== []) {
            $this->components->warn('Accelerator works, but host warnings need review.');
            $this->components->bulletList($warnings);
        } else {
            $this->components->success('Accelerator is installed and ready.');
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function inspectRuntime(): void
    {
        $this->assert('Runtime', 'PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.5.0', '>='), 'PHP 8.5 or newer is required.');
        $this->assert('Runtime', 'Laravel', Application::VERSION, str_starts_with(Application::VERSION, '13.'), 'Laravel 13 is required.');
        $packageVersion = InstalledVersions::getPrettyVersion('wireninja/accelerator') ?? 'source checkout';
        $this->assert('Runtime', 'Accelerator', $packageVersion, true, 'Accelerator is not installed.');

        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'] as $extension) {
            $this->assert('Runtime', "ext-{$extension}", extension_loaded($extension) ? 'loaded' : 'missing', extension_loaded($extension), "PHP extension {$extension} is required.");
        }

        $opcache = extension_loaded('Zend OPcache');
        $this->assert('Runtime', 'OPcache', $opcache ? ((bool) ini_get('opcache.enable') ? 'enabled' : 'disabled') : 'unavailable', $opcache, 'OPcache should be installed on production hosts.', 'warning');
        $this->assert('Runtime', 'HTTP runtime', 'PHP-FPM', true, 'Accelerator client applications require PHP-FPM.');
        $this->assert(
            'Runtime',
            'Package discovery',
            AcceleratorServiceProvider::class,
            app()->getProvider(AcceleratorServiceProvider::class) !== null,
            'Composer did not discover AcceleratorServiceProvider.',
        );
    }

    private function inspectRecipe(): void
    {
        $required = [
            'app/Enums/System/PanelEnum.php',
            'app/Enums/System/RoleEnum.php',
            'app/Models/User.php',
            'app/Providers/Filament/AdminPanelProvider.php',
            'app/Support/helpers.php',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'database/seeders/DatabaseSeeder.php',
            'phpstan.neon',
            'rector.php',
            'resources/css/app.css',
            'resources/css/filament/theme.css',
            'resources/js/app.ts',
            'resources/svg/.gitkeep',
            'resources/vendor/accelerator/leaflet/leaflet.js',
            'resources/vendor/accelerator/leaflet/leaflet.css',
            'routes/channels.php',
            'routes/console.php',
            'composer.lock',
            'public/build/manifest.json',
        ];
        $missing = array_values(array_filter($required, fn (string $file): bool => ! is_file(base_path($file))));

        $this->assert(
            'Install recipe',
            'Required files',
            $missing === [] ? count($required).' present' : implode(', ', $missing),
            $missing === [],
            'Required files are missing: '.implode(', ', $missing),
        );

        $userClass = config('auth.providers.users.model');
        $validUser = is_string($userClass)
            && class_exists($userClass)
            && is_subclass_of($userClass, Model::class)
            && is_subclass_of($userClass, AcceleratorUser::class);
        $this->assert('Install recipe', 'User model', is_string($userClass) ? $userClass : 'not configured', $validUser, 'The auth user model must implement AcceleratorUser.');

        $providers = $this->contents('bootstrap/providers.php');
        $this->assert('Install recipe', 'Admin panel', 'AdminPanelProvider', str_contains($providers, 'AdminPanelProvider::class'), 'AdminPanelProvider is not registered.');

        $rootRoutes = $this->contents('routes/web.php');
        $this->assert(
            'Install recipe',
            'Root route ownership',
            'userland',
            ! str_contains($rootRoutes, 'accelerator::') && ! str_contains($rootRoutes, 'WireNinja\\Accelerator'),
            'Accelerator must not own the public root route.',
        );
    }

    private function inspectFrontend(): void
    {
        $package = json_decode($this->contents('package.json'), true);
        $specification = is_array($package) && is_string($package['packageManager'] ?? null) ? $package['packageManager'] : '';
        $manager = str_starts_with($specification, 'pnpm@') ? 'pnpm' : (str_starts_with($specification, 'npm@') ? 'npm' : '');
        $lock = $manager === 'pnpm' ? 'pnpm-lock.yaml' : 'package-lock.json';
        $policy = $manager === 'pnpm' ? 'pnpm-workspace.yaml' : '.npmrc';
        $expectedVersion = $manager === '' ? '' : substr($specification, strlen($manager) + 1);
        $actualVersion = $manager === '' ? '' : $this->executableVersion($manager);

        $this->assert('Frontend', 'Package manager', $specification ?: 'invalid', $manager !== '', 'package.json must select pnpm or npm with an exact tool version.');
        $this->assert('Frontend', 'Package manager executable', $actualVersion ?: 'missing', $actualVersion !== '' && $actualVersion === $expectedVersion, "{$manager} executable version must match package.json ({$expectedVersion}).");
        $this->assert('Frontend', 'Selected lockfile', $lock, $manager !== '' && is_file(base_path($lock)), "Missing {$lock}.");

        $conflicts = array_values(array_filter(['pnpm-lock.yaml', 'package-lock.json', 'yarn.lock'], fn (string $file): bool => $file !== $lock && is_file(base_path($file))));
        $this->assert('Frontend', 'Conflicting lockfiles', $conflicts === [] ? 'none' : implode(', ', $conflicts), $conflicts === [], 'Delete lockfiles for package managers not selected by package.json.');
        $this->assert('Frontend', 'Release-age policy', $policy, $manager !== '' && is_file(base_path($policy)), "Missing supply-chain policy {$policy}.");
    }

    private function executableVersion(string $manager): string
    {
        if ((new ExecutableFinder)->find($manager) === null) {
            return '';
        }

        $process = new Process([$manager, '--version'], base_path());
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : '';
    }

    private function inspectConfiguration(): void
    {
        foreach (['oauth', 'pwa', 'telegram', 'realtime', 'scout', 'observability'] as $feature) {
            $key = 'ACCELERATOR_FEATURE_'.strtoupper($feature);
            $valid = true;

            foreach (['.env', '.env.example'] as $file) {
                $valid = $valid && preg_match('/^'.preg_quote($key, '/').'=(?:true|false)$/m', $this->contents($file)) === 1;
            }

            $this->assert('Configuration', $key, $valid ? 'explicit' : 'missing', $valid, "{$key} must be true or false in .env and .env.example.");
        }

        $this->assert('Configuration', 'Application key', filled(config('app.key')) ? 'set' : 'empty', filled(config('app.key')), 'APP_KEY is empty.');
        $upload = (int) config('accelerator.uploads.max_megabytes', 100);
        $this->assert('Configuration', 'Upload limit', "{$upload} MB", $upload > 0, 'ACCELERATOR_UPLOAD_MAX_MB must be positive.');
    }

    private function inspectDatabase(): void
    {
        try {
            $required = ['migrations', 'users', 'roles', 'permissions'];
            $missing = array_values(array_filter($required, fn (string $table): bool => ! Schema::hasTable($table)));
            $this->assert('Database', 'Core schema', $missing === [] ? 'ready' : implode(', ', $missing), $missing === [], 'Database schema is incomplete.');
        } catch (Throwable $exception) {
            $this->record('Database', 'Connection', (string) config('database.default'), 'error', 'Database is unavailable: '.$exception->getMessage());
        }
    }

    private function inspectHost(): void
    {
        $finder = new ExecutableFinder;
        $package = json_decode($this->contents('package.json'), true);
        $specification = is_array($package) && is_string($package['packageManager'] ?? null) ? $package['packageManager'] : 'pnpm@';
        $manager = str_starts_with($specification, 'npm@') ? 'npm' : 'pnpm';

        foreach (['composer', $manager, 'git'] as $executable) {
            $path = $finder->find($executable);
            $this->assert('Host', $executable, $path === null ? 'not found' : 'available', $path !== null, "{$executable} is not available on PATH.", 'warning');
        }

        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $this->assert('Host', "Writable {$directory}", is_writable(base_path($directory)) ? 'writable' : 'not writable', is_writable(base_path($directory)), "{$directory} must be writable.");
        }
    }

    private function inspectSecurity(): void
    {
        $productionDebug = app()->isProduction() && (bool) config('app.debug');
        $this->assert('Security', 'Production debug', $productionDebug ? 'enabled' : 'disabled', ! $productionDebug, 'APP_DEBUG must be false in production.');
        $this->assert('Security', 'Application key', filled(config('app.key')) ? 'set' : 'empty', filled(config('app.key')), 'APP_KEY is empty.');

    }

    private function inspectSection(string $section): void
    {
        match ($section) {
            'runtime' => $this->inspectRuntimeSection(),
            'database' => $this->inspectDatabase(),
            'frontend' => $this->inspectFrontend(),
            'security' => $this->inspectSecurity(),
            default => throw new InvalidArgumentException("Unknown doctor section: {$section}"),
        };
    }

    private function inspectRuntimeSection(): void
    {
        $this->inspectRuntime();
        $this->inspectRecipe();
        $this->inspectConfiguration();
        $this->inspectHost();
    }

    /** @param 'warning'|'error' $failureStatus */
    private function assert(string $category, string $label, string $value, bool $passed, string $message, string $failureStatus = 'error'): void
    {
        $this->record($category, $label, $value, $passed ? 'ok' : $failureStatus, $passed ? null : $message);
    }

    /** @param 'ok'|'warning'|'error' $status */
    private function record(string $category, string $label, string $value, string $status, ?string $message): void
    {
        $value = $this->redact($value);
        $message = is_string($message) ? $this->redact($message) : null;
        $this->checks[] = compact('category', 'label', 'value', 'status', 'message');
    }

    private function contents(string $path): string
    {
        $contents = @file_get_contents(base_path($path));

        return is_string($contents) ? $contents : '';
    }

    /** @return list<string> */
    private function messagesFor(string $status): array
    {
        return array_values(array_map(
            fn (array $check): string => $check['message'] ?? $check['label'],
            array_filter($this->checks, fn (array $check): bool => $check['status'] === $status),
        ));
    }

    /** @return array<string, list<array{category: string, label: string, value: string, status: 'ok'|'warning'|'error', message: string|null}>> */
    private function groupedChecks(): array
    {
        $groups = [];

        foreach ($this->checks as $check) {
            $groups[$check['category']][] = $check;
        }

        return $groups;
    }

    private function redact(string $value): string
    {
        $value = str_replace(base_path(), '[project]', $value);
        $value = (string) preg_replace('#/Users/[^/]+#', '/Users/[redacted]', $value);
        $value = (string) preg_replace('/(password|secret|token|key)=([^\s&]+)/i', '$1=[redacted]', $value);

        return $value;
    }
}
