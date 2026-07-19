<?php

namespace WireNinja\Accelerator\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuth2User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;
use WireNinja\Accelerator\Services\GoogleOAuthService;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to the Google OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     */
    public function callback(GoogleOAuthService $service): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            if (! $googleUser instanceof OAuth2User) {
                throw new \RuntimeException('Google OAuth did not return an OAuth 2 user.');
            }

            $service->handle($googleUser);

            return redirect()->intended(config('filament.path', 'admin'));
        } catch (Throwable) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => 'Gagal login menggunakan Google.']);
        }
    }
}
