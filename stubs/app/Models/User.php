<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Filament\AvatarProviders\DiceBearAvatarProvider;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property string|null $telegram_chat_id
 * @property bool $has_email_authentication
 * @property string|null $app_authentication_secret
 * @property array<array-key, mixed>|null $app_authentication_recovery_codes
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property string|null $avatar
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $google_id
 * @property CarbonImmutable|null $suspended_at
 * @property int|null $suspended_by
 * @property string|null $suspension_reason
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements AcceleratorUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPushSubscriptions;
    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use InteractsWithEmailAuthentication;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'has_email_authentication' => 'boolean',
            'suspended_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** @return BelongsTo<User, $this> */
    public function suspender(): BelongsTo
    {
        return $this->belongsTo(self::class, 'suspended_by');
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function getAppAuthenticationHolderName(): string
    {
        return filled($this->username) ? $this->username : $this->email;
    }

    public function routeNotificationForTelegram(): ?string
    {
        return filled($this->telegram_chat_id) ? $this->telegram_chat_id : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (! $this->isSuspended()) && $this->hasVerifiedEmail();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ? Storage::url($this->avatar) : (new DiceBearAvatarProvider)->get($this);
    }

    public function isSuspended(): bool
    {
        return filled($this->suspended_at);
    }

    public function suspend(?AuthenticatableContract $suspender = null, ?string $reason = null): static
    {
        $this->forceFill([
            'suspended_at' => now(),
            'suspended_by' => $suspender?->getAuthIdentifier(),
            'suspension_reason' => filled($reason) ? $reason : $this->suspension_reason,
        ])->save();

        return $this->refresh();
    }

    public function unsuspend(): static
    {
        $this->forceFill([
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ])->save();

        return $this->refresh();
    }

    public function canImpersonate(): bool
    {
        return $this->isSuperAdmin();
    }
}
