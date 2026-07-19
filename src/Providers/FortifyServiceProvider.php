<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider as LaravelFortifyServiceProvider;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Support\UserModel;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(LaravelFortifyServiceProvider::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', static function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by(sha1($email.'|'.($request->ip() ?? 'unknown')));
        });

        Fortify::authenticateUsing(static function (Request $request): ?Authenticatable {
            $email = strtolower(trim((string) $request->input('email')));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            $user = UserModel::query()->where('email', $email)->first();

            if (! $user instanceof AcceleratorUser || $user->isSuspended()) {
                return null;
            }

            $password = $user->getAuthPassword();

            return $password !== '' && Hash::check((string) $request->input('password'), $password)
                ? $user
                : null;
        });
    }
}
