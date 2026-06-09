<?php

namespace WireNinja\Accelerator\Filament;

use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
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
use WireNinja\Accelerator\Constant\Profile;
use WireNinja\Accelerator\Filament\AvatarProviders\DiceBearAvatarProvider;
use WireNinja\Accelerator\Filament\Pages\Auth\Login;
use WireNinja\Accelerator\Filament\Pages\ManageProfile;
use WireNinja\Accelerator\Livewire\Sidebar;
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
            ->defaultAvatarProvider(DiceBearAvatarProvider::class)
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
                'primary' => [
                    50 => '#fafafa',
                    100 => '#f4f4f5',
                    200 => '#e4e4e7',
                    300 => '#d4d4d8', // Dark mode hover
                    400 => '#ffffff', // Dark mode main
                    500 => '#000000', // Light mode main
                    600 => '#18181b', // Light mode hover
                    700 => '#27272a',
                    800 => '#3f3f46',
                    900 => '#52525b',
                    950 => '#71717a',
                ],
                'secondary' => Color::Gray,
                'success' => Color::Emerald,
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'info' => Color::Sky,
                'gray' => Color::Zinc,
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
                // AccountWidget::class,
                // FilamentInfoWidget::class,
                // SystemInfoWidget::class,
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
            ])
            ->databaseNotifications()
            ->broadcasting(fn () => config('broadcasting.default') === 'reverb')
            ->spa()
            ->topbar(false)
            ->globalSearch(false)
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->collapsibleNavigationGroups()
            ->sidebarFullyCollapsibleOnDesktop()
            ->databaseTransactions()
            ->unsavedChangesAlerts(fn () => resolve('app')->isProduction())
            ->strictAuthorization(fn () => resolve('app')->isLocal())
            ->profile(ManageProfile::class, isSimple: true)
            ->revealablePasswords()
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->errorNotifications()
            ->hiddenErrorNotification(BuiltinExceptions::getFilamentBusinessExceptionStatusCode())
            ->lazyLoadedDatabaseNotifications()
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => config('services.google.client_id') ? view('accelerator::filament.auth.google-login') : ''
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('accelerator::filament.sidebar.notice')
            )
            ->renderHook(
                AcceleratorPanelsRenderHook::SIDEBAR_SUPPORT,
                fn () => view('accelerator::filament.sidebar.support')
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => view('accelerator::filament.sidebar.topbar')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('accelerator::partials.pwa.head')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('accelerator::filament.business-exception-handler', BuiltinExceptions::getFilamentBusinessExceptionViewData())
            )
            ->bootUsing(function (Panel $panel) {
                rescue(function () use ($panel) {
                    $settings = resolve(SystemSettings::class);

                    $panel
                        ->font($settings->google_font->value, provider: GoogleFontProvider::class)
                        ->registration($settings->registration_enabled ? Register::class : null)
                        ->passwordReset(
                            $settings->password_reset_enabled ? RequestPasswordReset::class : null,
                            $settings->password_reset_enabled ? ResetPassword::class : null,
                        )
                        ->brandName($settings->brand_name)
                        ->brandLogo(self::resolveAssetUrl($settings->brand_logo))
                        ->favicon(self::resolveAssetUrl($settings->brand_favicon))
                        ->emailVerification(
                            $settings->email_verification_enabled ? EmailVerificationPrompt::class : null,
                            $settings->email_verification_enabled,
                        )
                        ->emailChangeVerification($settings->email_verification_enabled ? true : false)
                        ->userMenuItems([
                            Action::make('whatsapp_support')
                                ->label('Whatsapp Support')
                                ->url(fn () => sprintf('https://wa.me/%s', Profile::DEVELOPER_WHATSAPP))
                                ->openUrlInNewTab()
                                ->visible(fn () => ! blank(Profile::DEVELOPER_WHATSAPP) && $settings->support_enabled)
                                ->icon('lucide-phone-outgoing'),
                            Action::make('telegram_support')
                                ->label('Telegram Support')
                                ->url(fn () => sprintf('https://t.me/%s', Profile::DEVELOPER_TELEGRAM))
                                ->openUrlInNewTab()
                                ->visible(fn () => ! blank(Profile::DEVELOPER_TELEGRAM) && $settings->support_enabled)
                                ->icon('lucide-send'),
                        ]);
                });

                return $panel;
            });
    }

    /**
     * @DONOT-REMOVE setRenderHook helper
     *
     * Internal development tool: when called from `configure()` (uncomment the
     * `// self::setRenderHook($panel);` line), this method overlays every Filament
     * render hook (panel, table, widget) with a red-bordered box showing the hook name.
     * Useful when designing custom views/extensions to identify hook positions.
     *
     * Intentionally NOT called in production. Do not remove, do not make public.
     * If a future agent deletes this as "dead code", the developer loses a fast way
     * to map render hooks without reading Filament documentation.
     *
     * Usage:
     *   - Uncomment `self::setRenderHook($panel);` at the start of `configure()`.
     *   - Open the admin panel in a browser.
     *   - See red-bordered hook labels at every render point.
     *   - When done, comment the line back.
     */
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
