<?php

namespace WireNinja\Accelerator\Console\Agent;

use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use WireNinja\Accelerator\Console\Concerns\HasBanner;
use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Support\EnvReader;

#[Signature('agent:audit {--json : Output as JSON} {--compact : Compact JSON output}')]
#[Description('Comprehensive system environment audit for Laravel/Filament')]
class AuditCommand extends Command
{
    use HasBanner;

    protected string $minPhpVersion = '8.5.0';

    protected string $minPostMaxSize = '100M';

    protected array $requiredExtensions = [
        'bcmath',
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'filter',
        'gd',
        'hash',
        'intl',
        'json',
        'mbstring',
        'openssl',
        'pcre',
        'pdo',
        'pdo_mysql',
        'session',
        'tokenizer',
        'xml',
        'xmlwriter',
        'zip',
        'redis',
        'swoole',
        'imagick',
    ];

    public function handle(): int
    {
        $warnings = [];
        $results = [];

        $results['php_core'] = $this->checkPhpCore($warnings);
        $results['resource_limits'] = $this->checkResourceLimits($warnings);
        $results['extensions'] = $this->checkExtensions($warnings);
        $results['performance'] = $this->checkPerformance($warnings);
        $results['build_tools'] = $this->checkBuildTools($warnings);
        $results['accelerator_integration'] = $this->checkAcceleratorIntegration($warnings);
        $results['environment'] = $this->checkEnvironment($warnings);

        if ($this->option('json')) {
            $payload = [
                'status' => empty($warnings) ? 'OK' : 'WARNING',
                'warnings' => $warnings,
                'results' => $results,
            ];

            $this->output->writeln(json_encode(
                $payload,
                ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));

            return empty($warnings) ? 0 : 1;
        }

        $this->displayBanner();

        $this->components->info(' [ 1/7 ] PHP Core');
        $this->displayTable(['Core', 'Current', 'Status'], $results['php_core']['rows']);

        $this->components->info(' [ 2/7 ] Resource Limits');
        $this->displayTable(['Resource Limits', 'Current', 'Status'], $results['resource_limits']['rows']);

        $this->components->info(' [ 3/7 ] Extensions');
        $this->displayTable(['Extension', 'Status'], $results['extensions']['rows']);

        $this->components->info(' [ 4/7 ] Performance (OPCache & JIT)');
        $this->displayTable(['Performance', 'Status'], $results['performance']['rows']);
        if ($results['performance']['opcache_enabled']) {
            $this->components->bulletList($results['performance']['opcache_info']);
        }
        if ($results['performance']['jit_enabled']) {
            $this->components->bulletList($results['performance']['jit_info']);
        }

        $this->components->info(' [ 5/7 ] Build Tools');
        $this->displayTable(['Build Tool', 'Status', 'Info'], $results['build_tools']['rows']);

        $this->components->info(' [ 6/7 ] Accelerator Integration');
        $this->displayTable(['Accelerator Integration', 'Status'], $results['accelerator_integration']['rows']);

        $this->components->info(' [ 7/7 ] Environment Verification');
        $this->displayTable(['ENV Key', 'Value', 'Status'], $results['environment']['rows']);

        $this->displayWarnings($warnings);

        return empty($warnings) ? 0 : 1;
    }

    protected function checkPhpCore(array &$warnings): array
    {
        $phpVersion = PHP_VERSION;
        $isOk = version_compare($phpVersion, $this->minPhpVersion, '>=');

        if (! $isOk) {
            $warnings[] = "PHP Version is $phpVersion, but $this->minPhpVersion is required.";
        }

        return [
            'version' => $phpVersion,
            'sapi' => PHP_SAPI,
            'rows' => [
                ['PHP Version', $phpVersion, $isOk ? '<fg=green>OK</>' : '<fg=red>LOW</>'],
                ['Interface', PHP_SAPI, 'DEBUG'],
            ],
        ];
    }

    protected function checkResourceLimits(array &$warnings): array
    {
        $limits = [
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_vars' => ini_get('max_input_vars'),
        ];

        $rows = [];
        foreach ($limits as $key => $value) {
            $status = '<fg=green>OK</>';
            if (($key === 'post_max_size' || $key === 'upload_max_filesize') && $this->formatBytes($value) < $this->formatBytes($this->minPostMaxSize)) {
                $status = '<fg=red>LOW</>';
                $warnings[] = "$key is $value, but at least $this->minPostMaxSize is required.";
            }
            $rows[] = [$key, $value, $status];
        }

        return [
            'limits' => $limits,
            'rows' => $rows,
        ];
    }

    protected function checkExtensions(array &$warnings): array
    {
        $rows = [];
        $statusMap = [];
        foreach ($this->requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            if (! $loaded) {
                $warnings[] = "Extension '$ext' is missing.";
            }
            $rows[] = [$ext, $loaded ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
            $statusMap[$ext] = $loaded;
        }

        return [
            'extensions' => $statusMap,
            'rows' => $rows,
        ];
    }

    protected function checkPerformance(array &$warnings): array
    {
        $opcacheStatus = function_exists('opcache_get_status') ? opcache_get_status() : false;
        $opcacheEnabled = $opcacheStatus !== false;
        $jitEnabled = ini_get('opcache.jit') !== 'off' && ini_get('opcache.jit') !== '0' && $opcacheEnabled;

        if (! $opcacheEnabled) {
            $warnings[] = 'OPCache is disabled.';
        }
        if (! $jitEnabled) {
            $warnings[] = 'Jit is disabled or inactive.';
        }

        $opcacheInfo = [];
        if ($opcacheEnabled) {
            $opcacheInfo = [
                'Memory: Used '.round($opcacheStatus['memory_usage']['used_memory'] / 1024 / 1024, 2).' MB / Free '.round($opcacheStatus['memory_usage']['free_memory'] / 1024 / 1024, 2).' MB',
                'Hits: '.$opcacheStatus['opcache_statistics']['hits'].' (Rate: '.round($opcacheStatus['opcache_statistics']['opcache_hit_rate'], 2).'%)',
            ];
        }

        $jitInfo = [];
        if ($jitEnabled) {
            $jitStatus = $opcacheStatus['jit'] ?? [];
            $jitInfo = [
                'JIT Strategy: '.ini_get('opcache.jit'),
                isset($jitStatus['buffer_size'])
                    ? 'JIT Buffer: Used '.round(($jitStatus['buffer_size'] - $jitStatus['buffer_free']) / 1024 / 1024, 2).' MB / Max '.round($jitStatus['buffer_size'] / 1024 / 1024, 2).' MB'
                    : 'JIT Buffer: N/A',
            ];
        }

        return [
            'opcache_enabled' => $opcacheEnabled,
            'jit_enabled' => $jitEnabled,
            'opcache_info' => $opcacheInfo,
            'jit_info' => $jitInfo,
            'rows' => [
                ['OPCache', $opcacheEnabled ? '<fg=green>ENABLED</>' : '<fg=red>DISABLED</>'],
                ['JIT', $jitEnabled ? '<fg=green>ENABLED</>' : '<fg=red>DISABLED</>'],
            ],
        ];
    }

    protected function checkBuildTools(array &$warnings): array
    {
        $tools = [
            'Composer' => 'composer --version 2>/dev/null',
            'Bun' => 'bun --version 2>/dev/null',
            'Npm' => 'npm --version 2>/dev/null',
            'Git' => 'git --version 2>/dev/null',
        ];

        $rows = [];
        $infoMap = [];
        foreach ($tools as $name => $cmd) {
            $output = shell_exec($cmd);
            if (! $output) {
                $warnings[] = "Tool '$name' is missing.";
            }
            $rows[] = [$name, $output ? '<fg=green>OK</>' : '<fg=red>MISSING</>', trim($output ?: 'N/A')];
            $infoMap[$name] = trim($output ?: 'N/A');
        }

        return [
            'tools' => $infoMap,
            'rows' => $rows,
        ];
    }

    protected function checkAcceleratorIntegration(array &$warnings): array
    {
        $rows = [];
        $integrationStatus = [];

        // 5. Check AdminPanelProvider
        if (class_exists(AdminPanelProvider::class)) {
            $providerContent = File::get(base_path('app/Providers/Filament/AdminPanelProvider.php'));
            $usesPreset = str_contains($providerContent, 'PanelPreset::configure');
            if (! $usesPreset) {
                $warnings[] = 'AdminPanelProvider is not using PanelPreset::configure().';
            }
            $rows[] = ['Provider: PanelPreset', $usesPreset ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
            $integrationStatus['panel_preset'] = $usesPreset;
        }

        // 6. Check User Model
        if (class_exists(User::class)) {
            $userModel = new ReflectionClass(User::class);
            $isAccelerated = $userModel->isSubclassOf(AcceleratedUser::class);
            if (! $isAccelerated) {
                $warnings[] = 'App\Models\User must extend WireNinja\Accelerator\Model\AcceleratedUser.';
            }
            $rows[] = ['Model: AcceleratedUser', $isAccelerated ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
            $integrationStatus['accelerated_user'] = $isAccelerated;
        }

        // 7. Check Mandatory Enums
        $enums = [
            'app/Enums/System/PanelEnum.php',
            'app/Enums/System/ResourceEnum.php',
            'app/Enums/System/RoleEnum.php',
        ];

        foreach ($enums as $enumFile) {
            $exists = File::exists(base_path($enumFile));
            if (! $exists) {
                $warnings[] = "Mandatory enum '{$enumFile}' is missing. Run 'php artisan accelerator:install'.";
            }
            $rows[] = ['Enum: '.basename($enumFile), $exists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
            $integrationStatus['enum_'.basename($enumFile, '.php')] = $exists;
        }

        // 8. Check Essential Files & Folders
        $faviconExists = File::exists(public_path('favicon.svg'));
        if (! $faviconExists) {
            $warnings[] = 'public/favicon.svg is missing.';
        }
        $rows[] = ['File: favicon.svg', $faviconExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
        $integrationStatus['favicon'] = $faviconExists;

        $svgFolderExists = File::isDirectory(resource_path('svg'));
        if (! $svgFolderExists) {
            $warnings[] = 'resources/svg folder is missing.';
        }
        $rows[] = ['Folder: resources/svg', $svgFolderExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
        $integrationStatus['svg_folder'] = $svgFolderExists;

        $storageLinkExists = is_link(public_path('storage'));
        if (! $storageLinkExists) {
            $warnings[] = 'public/storage is not a symlink. Run "php artisan storage:link".';
        }
        $rows[] = ['Link: public/storage', $storageLinkExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
        $integrationStatus['storage_link'] = $storageLinkExists;

        // 9. Check Essential Providers in bootstrap/providers.php
        if (File::exists(base_path('bootstrap/providers.php'))) {
            $providersContent = File::get(base_path('bootstrap/providers.php'));

            $essentialProviders = [
                'AppServiceProvider' => 'AppServiceProvider::class',
                'AcceleratorServiceProvider' => 'AcceleratorServiceProvider::class',
                'HorizonServiceProvider' => 'HorizonServiceProvider::class',
            ];

            foreach ($essentialProviders as $name => $class) {
                $exists = str_contains($providersContent, $class);
                if (! $exists) {
                    $warnings[] = "{$name} is not registered in bootstrap/providers.php.";
                }
                $rows[] = ["Provider: {$name}", $exists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
                $integrationStatus['provider_'.$name] = $exists;
            }
        }

        // 10. Check bootstrap/app.php Integration
        if (File::exists(base_path('bootstrap/app.php'))) {
            $appContent = File::get(base_path('bootstrap/app.php'));

            $hasMiddleware = str_contains($appContent, 'BuiltinMiddleware::make');
            if (! $hasMiddleware) {
                $warnings[] = 'BuiltinMiddleware::make($middleware) is missing in bootstrap/app.php.';
            }
            $rows[] = ['App: Middleware', $hasMiddleware ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
            $integrationStatus['app_middleware'] = $hasMiddleware;

            $hasExceptions = str_contains($appContent, 'BuiltinExceptions::make');
            if (! $hasExceptions) {
                $warnings[] = 'BuiltinExceptions::make($exceptions) is missing in bootstrap/app.php.';
            }
            $rows[] = ['App: Exceptions', $hasExceptions ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
            $integrationStatus['app_exceptions'] = $hasExceptions;
        }

        // 11. Check Schedule in routes/console.php
        if (File::exists(base_path('routes/console.php'))) {
            $consoleContent = File::get(base_path('routes/console.php'));

            $schedules = [
                'DB Backup' => 'BuiltinSystemSchedule::dbBackup',
                'Files Backup' => 'BuiltinSystemSchedule::filesBackup',
                'Horizon Snapshot' => 'BuiltinSystemSchedule::snapshotHorizon',
            ];

            foreach ($schedules as $name => $pattern) {
                $exists = str_contains($consoleContent, $pattern);
                if (! $exists) {
                    $warnings[] = "BuiltinSystemSchedule::{$name} is not registered in routes/console.php.";
                }
                $rows[] = ["Schedule: {$name}", $exists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
                $integrationStatus['schedule_'.$name] = $exists;
            }
        }

        return [
            'integration' => $integrationStatus,
            'rows' => $rows,
        ];
    }

    protected function checkEnvironment(array &$warnings): array
    {
        $allRedacted = EnvReader::redacted();
        $rows = [];

        foreach ($allRedacted as $key => $value) {
            $isMissing = ($value === '[EMPTY]' || $value === '[MISSING]');

            $status = ($value === '[REDACTED]' || ! $isMissing)
                ? '<fg=green>SET</>'
                : '<fg=gray>EMPTY</>';

            $rows[] = [$key, $value, $status];
        }

        return [
            'env' => $allRedacted,
            'rows' => $rows,
        ];
    }

    protected function displayTable(array $headers, array $rows): void
    {
        $this->table($headers, $rows);
        $this->newLine();
    }

    protected function displayWarnings(array $warnings): void
    {
        if (empty($warnings)) {
            $this->components->success('SYSTEM OK - READY FOR PRODUCTION');
        } else {
            $this->components->error('SYSTEM WARNINGS DETECTED:');
            foreach ($warnings as $index => $warning) {
                $this->line('  <fg=red>'.($index + 1).".</> $warning");
            }
        }
        $this->newLine();
    }

    protected function formatBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $res = (int) $val;
        switch ($last) {
            case 'g':
                $res *= 1024;
            case 'm':
                $res *= 1024;
            case 'k':
                $res *= 1024;
        }

        return $res;
    }
}
