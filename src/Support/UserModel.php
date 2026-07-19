<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use WireNinja\Accelerator\Contracts\AcceleratorUser;

final class UserModel
{
    /** @return class-string<Model> */
    public static function className(): string
    {
        $class = config('auth.providers.users.model');

        if (
            (! is_string($class))
            || (! is_subclass_of($class, Model::class))
            || (! is_subclass_of($class, AcceleratorUser::class))
        ) {
            throw new LogicException('The configured auth user model must extend Eloquent Model and implement '.AcceleratorUser::class.'.');
        }

        return $class;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        $class = self::className();

        return $class::query();
    }

    /** @return Model&AcceleratorUser */
    public static function current(): AcceleratorUser
    {
        self::className();
        $user = Auth::user();

        if (! $user instanceof AcceleratorUser) {
            throw new UnauthorizedHttpException('Accelerator', 'User is not authenticated with a compatible user model.');
        }

        return $user;
    }

    public static function id(?AcceleratorUser $user = null): int
    {
        $identifier = ($user ?? self::current())->getAuthIdentifier();

        if (is_int($identifier)) {
            return $identifier;
        }

        if (is_string($identifier) && ctype_digit($identifier)) {
            return (int) $identifier;
        }

        throw new LogicException('Accelerator requires an integer auth user identifier.');
    }
}
