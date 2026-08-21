<?php

namespace WireNinja\Accelerator\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use WireNinja\Accelerator\Filament\Pages\Auth\Login;
use WireNinja\Accelerator\Filament\Pages\ManageProfile;
use WireNinja\Accelerator\Filament\Pages\ManageSystemSettings;
use WireNinja\Accelerator\Settings\SystemSettings;
use WireNinja\Accelerator\Support\BuiltinExceptions;
use WireNinja\Accelerator\Support\Cast;

final class PanelPreset
{
    public static function configure(Panel $panel, string $id = 'admin'): Panel
    {
        $panelSegment = $id === 'admin' ? null : Str::studly($id);
        $panelDirectory = app_path('Filament'.($panelSegment ? "/{$panelSegment}" : ''));
        $panelNamespace = 'App\\Filament'.($panelSegment ? "\\{$panelSegment}" : '');
        $navigationGroupEnum = Cast::unitEnumClass(config('accelerator.enums.navigation_group'));

        return $panel
            ->id($id)
            ->path($id)
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
            ->databaseNotificationsPolling(null)
            ->broadcasting(static fn (): bool => config('broadcasting.default') === 'reverb')
            ->spa()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->when(
                $id === 'admin',
                static fn (Panel $configuredPanel): Panel => $configuredPanel
                    ->plugin(
                        FilamentShieldPlugin::make()
                            ->navigationGroup('System'),
                    )
                    ->pages([ManageSystemSettings::class]),
            )
            ->when(
                $navigationGroupEnum !== null,
                static fn (Panel $configuredPanel): Panel => $configuredPanel->navigationGroups(Cast::mustUnitEnumClass($navigationGroupEnum)),
            )
            ->databaseTransactions()
            ->unsavedChangesAlerts(static fn (): bool => app()->isProduction())
            ->strictAuthorization(static fn (): bool => app()->isLocal())
            ->profile(ManageProfile::class, isSimple: true)
            ->revealablePasswords()
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->errorNotifications()
            ->hiddenErrorNotification(BuiltinExceptions::getFilamentBusinessExceptionStatusCode())
            ->lazyLoadedDatabaseNotifications()
            ->bootUsing(static function (Panel $panel): void {
                $settings = resolve(SystemSettings::class);
                $whatsapp = preg_replace('/\D+/', '', Cast::mustString(config('accelerator.support.whatsapp')));
                $telegram = ltrim(Cast::mustString(config('accelerator.support.telegram')), '@');

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
