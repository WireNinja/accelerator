<?php

namespace WireNinja\Accelerator\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as OAuth2User;
use LogicException;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Support\UserModel;

class GoogleOAuthService
{
    /**
     * @param  SocialiteUser&OAuth2User  $googleUser
     */
    public function handle(SocialiteUser $googleUser): AcceleratorUser
    {
        $mode = (string) config('accelerator.oauth.mode', 'disabled');
        $email = strtolower(trim((string) $googleUser->getEmail()));
        $googleId = trim((string) $googleUser->getId());

        if ((! filter_var($email, FILTER_VALIDATE_EMAIL)) || ($googleId === '')) {
            throw new AuthenticationException('Google did not return a valid email and user ID.');
        }

        if (! in_array($mode, ['existing_only', 'allowed_domains'], true)) {
            throw new AuthenticationException('Google OAuth login is disabled.');
        }

        $user = UserModel::query()->where('email', $email)->first();

        if ($user === null) {
            if (($mode !== 'allowed_domains') || (! $this->domainIsAllowed($email))) {
                throw new AuthenticationException('No provisioned user matches this Google account.');
            }

            $userClass = UserModel::className();
            $user = new $userClass;
            $user->forceFill([
                'name' => filled($googleUser->getName()) ? $googleUser->getName() : str($email)->before('@')->toString(),
                'email' => $email,
                'google_id' => $googleId,
                'email_verified_at' => now(),
            ])->save();
        }

        if (! $user instanceof AcceleratorUser) {
            throw new LogicException('The configured user model does not implement '.AcceleratorUser::class.'.');
        }

        if ($user->isSuspended()) {
            throw new AuthenticationException('This user account is suspended.');
        }

        $storedGoogleId = trim((string) $user->getAttribute('google_id'));

        if (($storedGoogleId !== '') && (! hash_equals($storedGoogleId, $googleId))) {
            throw new AuthenticationException('This email is already linked to another Google identity.');
        }

        $attributes = ['google_id' => $googleId];

        if ($user->getAttribute('email_verified_at') === null) {
            $attributes['email_verified_at'] = now();
        }

        $user->forceFill($attributes)->save();

        Auth::login($user);

        return $user;
    }

    private function domainIsAllowed(string $email): bool
    {
        $domain = str($email)->afterLast('@')->lower()->toString();
        $allowedDomains = collect(config('accelerator.oauth.allowed_domains', []))
            ->filter(fn (mixed $allowedDomain): bool => is_string($allowedDomain))
            ->map(fn (string $allowedDomain): string => strtolower(trim($allowedDomain)))
            ->filter()
            ->all();

        return in_array($domain, $allowedDomains, true);
    }
}
