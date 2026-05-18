<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper around Laravel's AddLinkHeadersForPreloadedAssets that respects two
 * Accelerator config keys:
 *
 *   accelerator.middleware.link_preload.enabled              (bool, default true)
 *   accelerator.middleware.link_preload.skip_path_prefixes   (array, default ['admin', 'admin/*'])
 *
 * Filament admin pages render via Livewire and rarely benefit from preload
 * Link headers — skipping them removes header bloat and avoids the historical
 * "mixed content" smell the team hit on SSM.
 *
 * Config is the only source of truth. NEVER call env() outside config files
 * — env() returns null after `config:cache`.
 */
final class ConditionalLinkPreload
{
    public function __construct(private readonly AddLinkHeadersForPreloadedAssets $inner) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('accelerator.middleware.link_preload.enabled', true)) {
            return $next($request);
        }

        $skipPatterns = config('accelerator.middleware.link_preload.skip_path_prefixes', []);

        if (is_array($skipPatterns) && $skipPatterns !== [] && $request->is(...$skipPatterns)) {
            return $next($request);
        }

        return $this->inner->handle($request, $next);
    }
}
