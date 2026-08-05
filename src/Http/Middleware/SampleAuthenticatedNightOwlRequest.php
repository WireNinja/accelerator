<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Facades\Nightwatch;
use Symfony\Component\HttpFoundation\Response;

final class SampleAuthenticatedNightOwlRequest
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('accelerator.features.nightowl', false) || ! $request->user()) {
            Nightwatch::dontSample();

            return $next($request);
        }

        $rate = max(0.0, min(1.0, (float) config('accelerator.nightowl.authenticated_request_sample_rate', 1.0)));
        Nightwatch::sample($rate);

        return $next($request);
    }
}
