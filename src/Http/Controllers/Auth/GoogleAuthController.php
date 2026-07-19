<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Auth;

use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as OAuth2User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;
use WireNinja\Accelerator\Services\GoogleOAuthService;

final class GoogleAuthController extends Controller
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
    public function callback(Request $request, GoogleOAuthService $service): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->failedLogin('Login Google dibatalkan.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();

            if (! $googleUser instanceof OAuth2User) {
                throw new \RuntimeException('Google OAuth did not return an OAuth 2 user.');
            }

            $service->handle($googleUser);
            $request->session()->regenerate();

            return redirect()->intended(Filament::getPanel('admin')->getUrl());
        } catch (AuthenticationException|InvalidStateException) {
            return $this->failedLogin('Akun Google tidak diizinkan masuk.');
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->failedLogin('Google login sedang tidak tersedia.');
        }
    }

    private function failedLogin(string $message): RedirectResponse
    {
        $loginUrl = Filament::getPanel('admin')->getLoginUrl();

        return redirect()->to($loginUrl ?? '/admin/login')->withErrors(['email' => $message]);
    }
}
