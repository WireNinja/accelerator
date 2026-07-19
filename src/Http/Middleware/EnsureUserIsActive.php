<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use WireNinja\Accelerator\Contracts\AcceleratorUser;

final class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AcceleratorUser || ! $user->isSuspended()) {
            return $next($request);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => 'This user account is suspended.'], Response::HTTP_FORBIDDEN);
        }

        return new RedirectResponse(route('login'));
    }
}
