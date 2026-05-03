<?php

namespace WireNinja\Accelerator\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleOAuthService
{
    /**
     * Handle the data from a Google OAuth user and either create or login the user.
     */
    public function handle(SocialiteUser $googleUser): User
    {
        $user = User::updateOrCreate([
            'email' => $googleUser->getEmail(),
        ], [
            'name' => $googleUser->getName(),
            'google_id' => $googleUser->getId(),
            'google_token' => $googleUser->token,
            'google_refresh_token' => $googleUser->refreshToken,
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => now(),
        ]);

        Auth::login($user);

        return $user;
    }
}
