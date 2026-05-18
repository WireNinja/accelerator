<?php

namespace WireNinja\Accelerator\Services;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use WireNinja\Accelerator\Model\AcceleratedUser;

class AcceleratedUserService
{
    /**
     * @template TUser of AcceleratedUser
     *
     * @param  TUser  $user
     * @return TUser
     */
    public function suspend(AcceleratedUser $user, ?AuthenticatableContract $suspender = null, ?string $reason = null): AcceleratedUser
    {
        $user->forceFill([
            'suspended_at' => now(),
            'suspended_by' => $suspender?->getAuthIdentifier(),
            'suspension_reason' => filled($reason) ? $reason : $user->suspension_reason,
        ])->save();

        return $user->refresh();
    }

    /**
     * @template TUser of AcceleratedUser
     *
     * @param  TUser  $user
     * @return TUser
     */
    public function unsuspend(AcceleratedUser $user): AcceleratedUser
    {
        $user->forceFill([
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ])->save();

        return $user->refresh();
    }
}
