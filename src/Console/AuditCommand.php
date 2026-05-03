<?php

namespace WireNinja\Accelerator\Console;

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

#[Signature('accelerator:audit')]
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
        $this->displayBanner();

        $warnings = [];

        $this->components->info(' [ 1/7 ] PHP Core');
        $this->checkPhpCore($warnings);

        $this->components->info(' [ 2/7 ] Resource Limits');
        $this->checkResourceLimits($warnings);

        $this->components->info(' [ 3/7 ] Extensions');
        $this->checkExtensions($warnings);

        $this->components->info(' [ 4/7 ] Performance (OPCache & JIT)');
        $this->checkPerformance($warnings);

        $this->components->info(' [ 5/7 ] Build Tools');
        $this->checkBuildTools($warnings);

        $this->components->info(' [ 6/7 ] Accelerator Integration');
        $this->checkAcceleratorIntegration($warnings);

        $this->components->info(' [ 7/7 ] Environment Verification');
        $this->checkEnvironment($warnings);

        $this->displayWarnings($warnings);

        return empty($warnings) ? 0 : 1;
    }

    protected function checkPhpCore(array &$warnings): void
    {
        $phpVersion = PHP_VERSION;
        $isOk = version_compare($phpVersion, $this->minPhpVersion, '>=');

        if (! $isOk) {
            $warnings[] = "PHP Version is $phpVersion, but $this->minPhpVersion is required.";
        }

        $this->table(['Core', 'Current', 'Status'], [
            ['PHP Version', $phpVersion, $isOk ? '<fg=green>OK</>' : '<fg=red>LOW</>'],
            ['Interface', PHP_SAPI, 'DEBUG'],
        ]);
        $this->newLine();
    }

    protected function checkResourceLimits(array &$warnings): void
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

        $this->table(['Resource Limits', 'Current', 'Status'], $rows);
        $this->newLine();
    }

    protected function checkExtensions(array &$warnings): void
    {
        $rows = [];
        foreach ($this->requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            if (! $loaded) {
                $warnings[] = "Extension '$ext' is missing.";
            }
            $rows[] = [$ext, $loaded ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
        }

        $this->table(['Extension', 'Status'], $rows);
        $this->newLine();
    }

    protected function checkPerformance(array &$warnings): void
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

        $this->table(['Performance', 'Status'], [
            ['OPCache', $opcacheEnabled ? '<fg=green>ENABLED</>' : '<fg=red>DISABLED</>'],
            ['JIT', $jitEnabled ? '<fg=green>ENABLED</>' : '<fg=red>DISABLED</>'],
        ]);

        if ($opcacheEnabled) {
            $this->components->bulletList([
                'Memory: Used '.round($opcacheStatus['memory_usage']['used_memory'] / 1024 / 1024, 2).' MB / Free '.round($opcacheStatus['memory_usage']['free_memory'] / 1024 / 1024, 2).' MB',
                'Hits: '.$opcacheStatus['opcache_statistics']['hits'].' (Rate: '.round($opcacheStatus['opcache_statistics']['opcache_hit_rate'], 2).'%)',
            ]);
        }

        if ($jitEnabled) {
            $jitStatus = $opcacheStatus['jit'] ?? [];
            $this->components->bulletList([
                'JIT Strategy: '.ini_get('opcache.jit'),
                isset($jitStatus['buffer_size'])
                    ? 'JIT Buffer: Used '.round(($jitStatus['buffer_size'] - $jitStatus['buffer_free']) / 1024 / 1024, 2).' MB / Max '.round($jitStatus['buffer_size'] / 1024 / 1024, 2).' MB'
                    : 'JIT Buffer: N/A',
            ]);
        }
        $this->newLine();
    }

    protected function checkBuildTools(array &$warnings): void
    {
        $tools = [
            'Composer' => 'composer --version 2>/dev/null',
            'Bun' => 'bun --version 2>/dev/null',
            'Npm' => 'npm --version 2>/dev/null',
            'Git' => 'git --version 2>/dev/null',
        ];

        $rows = [];
        foreach ($tools as $name => $cmd) {
            $output = shell_exec($cmd);
            if (! $output) {
                $warnings[] = "Tool '$name' is missing.";
            }
            $rows[] = [$name, $output ? '<fg=green>OK</>' : '<fg=red>MISSING</>', trim($output ?: 'N/A')];
        }

        $this->table(['Build Tool', 'Status', 'Info'], $rows);
        $this->newLine();
    }

    protected function checkAcceleratorIntegration(array &$warnings): void
    {
        $rows = [];

        // 5. Check AdminPanelProvider
        if (class_exists(AdminPanelProvider::class)) {
            $providerContent = File::get(base_path('app/Providers/Filament/AdminPanelProvider.php'));
            $usesPreset = str_contains($providerContent, 'PanelPreset::configure');
            if (! $usesPreset) {
                $warnings[] = 'AdminPanelProvider is not using PanelPreset::configure().';
            }
            $rows[] = ['Provider: PanelPreset', $usesPreset ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
        }

        // 6. Check User Model
        if (class_exists(User::class)) {
            $userModel = new ReflectionClass(User::class);
            $isAccelerated = $userModel->isSubclassOf(AcceleratedUser::class);
            if (! $isAccelerated) {
                $warnings[] = 'App\Models\User must extend WireNinja\Accelerator\Model\AcceleratedUser.';
            }
            $rows[] = ['Model: AcceleratedUser', $isAccelerated ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
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
        }

        // 8. Check Essential Files & Folders
        $faviconExists = File::exists(public_path('favicon.svg'));
        if (! $faviconExists) {
            $warnings[] = 'public/favicon.svg is missing.';
        }
        $rows[] = ['File: favicon.svg', $faviconExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];

        $svgFolderExists = File::isDirectory(resource_path('svg'));
        if (! $svgFolderExists) {
            $warnings[] = 'resources/svg folder is missing.';
        }
        $rows[] = ['Folder: resources/svg', $svgFolderExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];

        $storageLinkExists = is_link(public_path('storage'));
        if (! $storageLinkExists) {
            $warnings[] = 'public/storage is not a symlink. Run "php artisan storage:link".';
        }
        $rows[] = ['Link: public/storage', $storageLinkExists ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];

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

            $hasExceptions = str_contains($appContent, 'BuiltinExceptions::make');
            if (! $hasExceptions) {
                $warnings[] = 'BuiltinExceptions::make($exceptions) is missing in bootstrap/app.php.';
            }
            $rows[] = ['App: Exceptions', $hasExceptions ? '<fg=green>OK</>' : '<fg=red>MISSING</>'];
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
            }
        }

        $this->table(['Accelerator Integration', 'Status'], $rows);
        $this->newLine();
    }

    protected function checkEnvironment(array &$warnings): void
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

        $this->table(['ENV Key', 'Value', 'Status'], $rows);
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
