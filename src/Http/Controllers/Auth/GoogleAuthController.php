<?php

namespace WireNinja\Accelerator\Http\Controllers\Auth;

use Exception;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
            $service->handle($googleUser);

            return redirect()->intended(config('filament.path', 'admin'));
        } catch (Exception $e) {
            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => 'Gagal login menggunakan Google.']);
        }
    }
}
