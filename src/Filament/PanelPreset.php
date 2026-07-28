<?php

namespace WireNinja\Accelerator\Filament;

use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use WireNinja\Accelerator\Filament\AvatarProviders\DiceBearAvatarProvider;
use WireNinja\Accelerator\Filament\Pages\Auth\Login;
use WireNinja\Accelerator\Filament\Pages\ManageProfile;
use WireNinja\Accelerator\Livewire\Sidebar;
use WireNinja\Accelerator\Livewire\Topbar;
use WireNinja\Accelerator\Settings\SystemSettings;
use WireNinja\Accelerator\Support\BuiltinExceptions;

final class PanelPreset
{
    public static function configure(Panel $panel, string $id = 'admin'): Panel
    {
        $panelSegment = $id === 'admin' ? null : Str::studly($id);
        $panelDirectory = app_path('Filament' . ($panelSegment ? "/{$panelSegment}" : ''));
        $panelNamespace = 'App\\Filament' . ($panelSegment ? "\\{$panelSegment}" : '');

        return $panel
            ->id($id)
            ->path($id)
            ->defaultAvatarProvider(DiceBearAvatarProvider::class)
            ->viteTheme(self::viteTheme($id))
            ->login(Login::class)
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
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
            ->topbarLivewireComponent(Topbar::class)
            ->sidebarWidth(sprintf('%dpx', (int) config('accelerator.ui.sidebar.default_width', 336)))
            ->collapsedSidebarWidth(sprintf('%dpx', (int) config('accelerator.ui.sidebar.rail_width', 52)))
            ->discoverResources(in: "{$panelDirectory}/Resources", for: "{$panelNamespace}\\Resources")
            ->discoverPages(in: "{$panelDirectory}/Pages", for: "{$panelNamespace}\\Pages")
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: "{$panelDirectory}/Widgets", for: "{$panelNamespace}\\Widgets")
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
            ->broadcasting(static fn(): bool => config('broadcasting.default') === 'reverb')
            ->spa()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->collapsibleNavigationGroups()
            ->sidebarCollapsibleOnDesktop()
            ->databaseTransactions()
            ->unsavedChangesAlerts(static fn(): bool => app()->isProduction())
            ->strictAuthorization(static fn(): bool => app()->isLocal())
            ->profile(ManageProfile::class, isSimple: true)
            ->revealablePasswords()
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->errorNotifications()
            ->hiddenErrorNotification(BuiltinExceptions::getFilamentBusinessExceptionStatusCode())
            ->lazyLoadedDatabaseNotifications()
            ->bootUsing(static function (Panel $panel): void {
                if (! config('accelerator.features.settings')) {
                    return;
                }

                $settings = resolve(SystemSettings::class);
                $whatsapp = preg_replace('/\D+/', '', (string) config('accelerator.support.whatsapp'));
                $telegram = ltrim((string) config('accelerator.support.telegram'), '@');

                $panel
                    ->font($settings->google_font->value, provider: GoogleFontProvider::class)
                    ->brandName($settings->brand_name)
                    ->brandLogo(self::resolveAssetUrl($settings->brand_logo))
                    ->favicon(self::resolveAssetUrl($settings->brand_favicon))
                    ->userMenuItems([
                        Action::make('whatsapp_support')
                            ->label('Whatsapp Support')
                            ->url("https://wa.me/{$whatsapp}")
                            ->openUrlInNewTab()
                            ->visible($settings->support_enabled && filled($whatsapp))
                            ->icon('lucide-phone-outgoing'),
                        Action::make('telegram_support')
                            ->label('Telegram Support')
                            ->url("https://t.me/{$telegram}")
                            ->openUrlInNewTab()
                            ->visible($settings->support_enabled && filled($telegram))
                            ->icon('lucide-send'),
                    ]);
            });
    }

    private static function viteTheme(string $path): string
    {
        $panelTheme = sprintf('resources/css/filament/%s/theme.css', $path);

        return is_file(base_path($panelTheme))
            ? $panelTheme
            : 'resources/css/filament/theme.css';
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
