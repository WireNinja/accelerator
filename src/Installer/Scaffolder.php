<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use RuntimeException;

final readonly class Scaffolder
{
    /** @var array<string, string> */
    private const RECIPE_FILES = [
        'stubs/app/Enums/System/NavigationGroup.php' => 'app/Enums/System/NavigationGroup.php',
        'stubs/app/Enums/System/PanelEnum.php' => 'app/Enums/System/PanelEnum.php',
        'stubs/app/Enums/System/RoleEnum.php' => 'app/Enums/System/RoleEnum.php',
        'stubs/app/Models/User.php' => 'app/Models/User.php',
        'stubs/app/Providers/Filament/AdminPanelProvider.php' => 'app/Providers/Filament/AdminPanelProvider.php',
        'stubs/app/Support/helpers.php' => 'app/Support/helpers.php',
        'stubs/bootstrap/app.php' => 'bootstrap/app.php',
        'stubs/bootstrap/providers.php.stub' => 'bootstrap/providers.php',
        'stubs/database/seeders/DatabaseSeeder.php' => 'database/seeders/DatabaseSeeder.php',
        'stubs/database/migrations/0001_01_01_000000_create_users_table.php' => 'database/migrations/0001_01_01_000000_create_users_table.php',
        'stubs/database/settings/2026_04_07_000001_create_system_settings.php' => 'database/settings/2026_04_07_000001_create_system_settings.php',
        'stubs/database/settings/2026_07_31_000001_remove_obsolete_system_settings.php' => 'database/settings/2026_07_31_000001_remove_obsolete_system_settings.php',
        'stubs/lang/vendor/filament-panels/id/auth/multi-factor/app/provider.php' => 'lang/vendor/filament-panels/id/auth/multi-factor/app/provider.php',
        'stubs/public/.user.ini' => 'public/.user.ini',
        'stubs/public/favicon.svg' => 'public/favicon.svg',
        'stubs/resources/svg/.gitkeep' => 'resources/svg/.gitkeep',
        'stubs/phpstan.neon' => 'phpstan.neon',
        'stubs/rector.php' => 'rector.php',
        'stubs/routes/console.php' => 'routes/console.php',
        'resources/install/routes/channels.php' => 'routes/channels.php',
        'resources/install/css/app.css' => 'resources/css/app.css',
        'resources/install/css/filament-theme.css' => 'resources/css/filament/theme.css',
        'resources/install/js/app.ts' => 'resources/js/app.ts',
        'resources/install/js/echo.ts' => 'resources/js/echo.ts',
        'resources/install/js/env.d.ts' => 'resources/js/env.d.ts',
        'resources/install/js/global.d.ts' => 'resources/js/global.d.ts',
        'resources/install/package.json' => 'package.json',
        'resources/install/eslint.config.js' => 'eslint.config.js',
        'resources/install/.prettierignore' => '.prettierignore',
        'resources/install/.prettierrc' => '.prettierrc',
        'resources/install/tsconfig.json' => 'tsconfig.json',
        'resources/install/vite.config.js' => 'vite.config.js',
    ];

    public function __construct(private InstallContext $context) {}

    public function install(): void
    {
        foreach ($this->recipeFiles() as $source => $destination) {
            $contents = file_get_contents($source);

            if (! is_string($contents)) {
                throw new RuntimeException("Unable to read recipe file: {$source}");
            }

            $this->context->writeFile($destination, $this->render($contents));
        }

        $replacedPath = $this->context->projectRoot.'/resources/js/app.js';

        if (is_file($replacedPath) && ! unlink($replacedPath)) {
            throw new RuntimeException('Unable to remove replaced Laravel file: resources/js/app.js');
        }
    }

    /** @return array<string, string> */
    public function recipeFiles(): array
    {
        $files = [];

        foreach (self::RECIPE_FILES as $source => $destination) {
            $files[$this->context->packageRoot.'/'.$source] = $destination;
        }

        return $files;
    }

    public function render(string $contents): string
    {
        return strtr($contents, [
            '{{ app_name }}' => $this->context->plan->appName,
            '{{ package_manager_spec }}' => $this->packageManagerSpecification(),
            '{{ provider_imports }}' => implode(PHP_EOL, [
                'use App\\Providers\\AppServiceProvider;',
                'use App\\Providers\\Filament\\AdminPanelProvider;',
            ]),
            '{{ providers }}' => implode(PHP_EOL, [
                '    AppServiceProvider::class,',
                '    AdminPanelProvider::class,',
            ]),
            '{{ pwa_enabled }}' => $this->context->boolean($this->context->hasFeature('pwa')),
        ]);
    }

    private function packageManagerSpecification(): string
    {
        $manager = $this->context->plan->packageManager;
        $version = $this->context->processRunner->capture([$manager, '--version'], $this->context->projectRoot);

        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new RuntimeException("Unable to resolve an exact {$manager} version.");
        }

        return "{$manager}@{$version}";
    }
}
