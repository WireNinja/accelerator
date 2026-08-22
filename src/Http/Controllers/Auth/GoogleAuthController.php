<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Auth;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as OAuth2User;
use RuntimeException;
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
        $provider = Socialite::driver('google');

        if (! $provider instanceof AbstractProvider) {
            throw new RuntimeException('Google OAuth did not resolve an OAuth 2 provider.');
        }

        return $provider
            ->with(['prompt' => 'select_account'])
            ->redirect();
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
                throw new RuntimeException('Google OAuth did not return an OAuth 2 user.');
            }

            $service->handle($googleUser);
            $request->session()->regenerate();

            return redirect()->intended(Filament::getPanel('admin')->getUrl());
        } catch (InvalidStateException $exception) {
            Log::notice('Google OAuth state validation failed.', [
                'exception' => $exception::class,
            ]);

            return $this->failedLogin('Sesi login Google tidak valid atau kedaluwarsa. Silakan coba lagi.');
        } catch (AuthenticationException $exception) {
            Log::notice('Google OAuth login rejected.', [
                'reason' => $exception->getMessage(),
            ]);

            return $this->failedLogin('Akun Google tidak diizinkan masuk.');
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->failedLogin('Google login sedang tidak tersedia.');
        }
    }

    private function failedLogin(string $message): RedirectResponse
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();

        $loginUrl = Filament::getPanel('admin')->getLoginUrl();

        return redirect()->to($loginUrl ?? '/admin/login');
    }
}
