<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;

final class OAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $mode = (string) config('accelerator.oauth.mode', 'disabled');

        if (! in_array($mode, ['existing_only', 'allowed_domains'], true)
            || blank(config('services.google.client_id'))
            || blank(config('services.google.client_secret'))) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/oauth.php');

        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            static fn (): View => view('accelerator::filament.auth.google-login'),
        );
    }
}
