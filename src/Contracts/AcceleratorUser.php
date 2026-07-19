<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Contracts;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Collection;

interface AcceleratorUser extends Authenticatable, Authorizable, FilamentUser, HasAvatar, MustVerifyEmail
{
    /** @return Collection<int, string> */
    public function getRoleNames(): Collection;

    public function initials(): string;

    public function isSuperAdmin(): bool;

    public function isSuspended(): bool;

    public function canImpersonate(): bool;
}
