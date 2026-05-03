<?php

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;
use WireNinja\Accelerator\Services\AcceleratedUserService;

/**
 * @property string $name
 * @property string|null $avatar
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $suspended_at
 * @property int|null $suspended_by
 * @property string|null $suspension_reason
 * @property string|null $telegram_chat_id
 * @property bool $receives_product_price_telegram_notifications
 * @property array<string>|null $app_authentication_recovery_codes
 * @property string|null $app_authentication_secret
 * @property bool $has_email_authentication
 * @property CarbonImmutable|null $two_factor_confirmed_at
 */
#[Table('users')]
class AcceleratedUser extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasEmailAuthentication
{
    use HasPushSubscriptions;
    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use InteractsWithEmailAuthentication;
    use Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'has_email_authentication' => 'boolean',
            'last_seen_at' => 'immutable_datetime',
            'receives_product_price_telegram_notifications' => 'boolean',
            'suspended_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function suspender(): BelongsTo
    {
        return $this->belongsTo(static::class, 'suspended_by');
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
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
        return $this->avatar ? Storage::url($this->avatar) : null;
    }

    public function isSuspended(): bool
    {
        return filled($this->suspended_at);
    }

    public function isOnline(int $thresholdMinutes = 5): bool
    {
        return $this->last_seen_at?->gte(now()->subMinutes($thresholdMinutes)) ?? false;
    }

    public function suspend(?AuthenticatableContract $suspender = null, ?string $reason = null): static
    {
        return $this->userService()->suspend($this, $suspender, $reason);
    }

    public function unsuspend(): static
    {
        return $this->userService()->unsuspend($this);
    }

    public function markAsOnline(): void
    {
        $this->userService()->touchOnlineStatus($this);
    }

    public function canImpersonate(): bool
    {
        return $this->isSuperAdmin();
    }

    protected function userService(): AcceleratedUserService
    {
        return resolve(AcceleratedUserService::class);
    }
}
