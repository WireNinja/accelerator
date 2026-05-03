<?php

namespace WireNinja\Accelerator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WireNinja\Accelerator\Model\AcceleratedUser;

class TrackOnlineStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user instanceof AcceleratedUser) {
            $user->markAsOnline();
        }

        return $response;
    }
}
