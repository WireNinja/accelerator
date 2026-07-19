<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

if (! function_exists('user')) {
    function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}

if (! function_exists('mustUser')) {
    function mustUser(): User
    {
        return user() ?? throw new UnauthorizedHttpException('Accelerator', 'User is not authenticated.');
    }
}
