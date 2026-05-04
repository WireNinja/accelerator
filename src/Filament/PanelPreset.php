<?php

namespace WireNinja\Accelerator\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Auth\Pages\Register;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Widgets\View\WidgetsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ReflectionClass;
use WireNinja\Accelerator\Filament\Pages\Auth\Login;
use WireNinja\Accelerator\Filament\Pages\ManageProfile;
use WireNinja\Accelerator\Http\Middleware\TrackOnlineStatus;
use WireNinja\Accelerator\Livewire\Sidebar;
use WireNinja\Accelerator\Livewire\SystemInfoWidget;
use WireNinja\Accelerator\Settings\SystemSettings;
use WireNinja\Accelerator\Support\BuiltinExceptions;

final class PanelPreset
{
    public static function configure(Panel $panel, string $id = 'admin'): Panel
    {
        // self::setRenderHook($panel);

        return $panel
            ->id($id)
            ->path($id)
            ->viteTheme([
                self::viteTheme($id),
                'vendor/wireninja/accelerator/resources/css/accelerator.css',
            ])
            ->login(Login::class)
            ->registration()
            ->passwordReset()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->recoveryCodeCount(10),
                EmailAuthentication::make()
                    ->codeExpiryMinutes(5),
            ])
            ->colors([
                'primary' => Color::Amber,
                'secondary' => Color::Slate,
                'success' => Color::Green,
                'danger' => Color::Red,
                'warning' => Color::Yellow,
                'info' => Color::Blue,
                'gray' => Color::Gray,
            ])
            ->maxContentWidth(Width::Full)
            ->sidebarLivewireComponent(Sidebar::class)
            ->sidebarWidth('25rem')
            ->discoverResources(in: self::discoverResourcesIn($id), for: self::discoverResourcesFor($id))
            ->discoverPages(in: self::discoverPagesIn($id), for: self::discoverPagesFor($id))
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: self::discoverWidgetsIn($id), for: self::discoverWidgetsFor($id))
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                SystemInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                TrackOnlineStatus::class,
            ])
            ->databaseNotifications()
            ->broadcasting(fn() => config('broadcasting.default') === 'reverb')
            ->spa()
            ->topbar(false)
            ->globalSearch(false)
            ->collapsibleNavigationGroups()
            ->sidebarFullyCollapsibleOnDesktop()
            ->databaseTransactions()
            ->unsavedChangesAlerts(fn() => resolve('app')->isProduction())
            ->strictAuthorization(fn() => resolve('app')->isLocal())
            ->profile(ManageProfile::class, isSimple: false)
            ->revealablePasswords()
            ->defaultThemeMode(ThemeMode::Light)
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->errorNotifications()
            ->hiddenErrorNotification(BuiltinExceptions::getFilamentBusinessExceptionStatusCode())
            ->lazyLoadedDatabaseNotifications()
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn() => config('services.google.client_id') ? view('accelerator::filament.auth.google-login') : ''
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn() => view('accelerator::partials.pwa.notice')
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn() => view('accelerator::filament.sidebar.support')
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn() => view('accelerator::filament.sidebar.toggle')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn() => view('accelerator::partials.pwa.head')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn() => view('accelerator::filament.business-exception-handler', BuiltinExceptions::getFilamentBusinessExceptionViewData())
            )
            ->bootUsing(function (Panel $panel) {
                rescue(function () use ($panel) {
                    $settings = resolve(SystemSettings::class);

                    $panel
                        ->font($settings->google_font->value, provider: GoogleFontProvider::class)
                        ->registration($settings->registration_enabled ? Register::class : null)
                        ->passwordReset($settings->password_reset_enabled ? true : null)
                        ->brandName($settings->brand_name)
                        ->brandLogo(self::resolveAssetUrl($settings->brand_logo))
                        ->favicon(self::resolveAssetUrl($settings->brand_favicon))
                        ->emailVerification($settings->email_verification_enabled ? true : null)
                        ->emailChangeVerification($settings->email_verification_enabled ? true : false);
                });

                return $panel;
            });
    }

    private static function setRenderHook(Panel $panel)
    {
        $panelHooks = new ReflectionClass(PanelsRenderHook::class);
        // Table Hooks
        $tableHooks = new ReflectionClass(TablesRenderHook::class);
        // Widget Hooks
        $widgetHooks = new ReflectionClass(WidgetsRenderHook::class);

        $panelHooks = $panelHooks->getConstants();
        $tableHooks = $tableHooks->getConstants();
        $widgetHooks = $widgetHooks->getConstants();

        foreach ($panelHooks as $hook) {
            $panel->renderHook($hook, function () use ($hook) {
                return Blade::render('<div style="border: solid red 1px; padding: 2px;">{{ $name }}</div>', [
                    'name' => Str::of($hook)->remove('tables::'),
                ]);
            });
        }
        foreach ($tableHooks as $hook) {
            $panel->renderHook($hook, function () use ($hook) {
                return Blade::render('<div style="border: solid red 1px; padding: 2px;">{{ $name }}</div>', [
                    'name' => Str::of($hook)->remove('tables::'),
                ]);
            });
        }
        foreach ($widgetHooks as $hook) {
            $panel->renderHook($hook, function () use ($hook) {
                return Blade::render('<div style="border: solid red 1px; padding: 2px;">{{ $name }}</div>', [
                    'name' => Str::of($hook)->remove('tables::'),
                ]);
            });
        }
    }

    private static function viteTheme(string $path): string
    {
        return sprintf('resources/css/filament/%s/theme.css', $path);
    }

    private static function discoverResourcesIn(string $id): string
    {
        if ($id === 'admin') {
            return app_path('Filament/Resources');
        }

        return app_path(sprintf('Filament/%s/Resources', Str::studly($id)));
    }

    private static function discoverResourcesFor(string $id): string
    {
        if ($id === 'admin') {
            return 'App\\Filament\\Resources';
        }

        return sprintf('App\\Filament\\%s\\Resources', Str::studly($id));
    }

    private static function discoverPagesIn(string $id): string
    {
        if ($id === 'admin') {
            return app_path('Filament/Pages');
        }

        return app_path(sprintf('Filament/%s/Pages', Str::studly($id)));
    }

    private static function discoverPagesFor(string $id): string
    {
        if ($id === 'admin') {
            return 'App\\Filament\\Pages';
        }

        return sprintf('App\\Filament\\%s\\Pages', Str::studly($id));
    }

    private static function discoverWidgetsIn(string $id): string
    {
        if ($id === 'admin') {
            return app_path('Filament/Widgets');
        }

        return app_path(sprintf('Filament/%s/Widgets', Str::studly($id)));
    }

    private static function discoverWidgetsFor(string $id): string
    {
        if ($id === 'admin') {
            return 'App\\Filament\\Widgets';
        }

        return sprintf('App\\Filament\\%s\\Widgets', Str::studly($id));
    }

    private static function resolveAssetUrl(?string $path, ?string $fallback = null): ?string
    {
        if (blank($path)) {
            return $fallback ? asset($fallback) : null;
        }

        if (str($path)->startsWith(['http://', 'https://']) || str($path)->startsWith('/')) {
            return str($path)->startsWith('/') ? url($path) : $path;
        }

        return Storage::url($path);
    }
}
