<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Keepsuit\LaravelOpenTelemetry\Facades\Tracer;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\HttpServerInstrumentation;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\Support\Http\Server\TraceRequestMiddleware;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WireNinja\Accelerator\Support\Cast;

final class TraceAuthenticatedRequest extends TraceRequestMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('accelerator.features.observability') || $request->user() === null) {
            return $next($request);
        }

        if ($request->is(HttpServerInstrumentation::getExcludedPaths())
            || in_array($request->method(), HttpServerInstrumentation::getExcludedMethods(), true)) {
            return $next($request);
        }

        $requestStartedAt = $this->requestStartTimestamp($request);
        $span = $this->startTracing($request, $requestStartedAt);
        $scope = $span->activate();
        $span->setAttribute('enduser.id', Cast::mustString($request->user()->getAuthIdentifier()));
        Tracer::updateLogContext();

        $bootedTimestamp = HttpServerInstrumentation::getBootedTimestamp() ?? Clock::getDefault()->now();

        if ($bootedTimestamp > $requestStartedAt) {
            Tracer::newSpan('app bootstrap')
                ->setStartTimestamp($requestStartedAt)
                ->start()
                ->end($bootedTimestamp);
        }

        try {
            $response = $next($request);

            if ($response instanceof Response) {
                $attributes = $this->sharedTraceMetricAttributes($request, $response);
                $this->recordTraceAttributes($span, $request, $response, $attributes);
                $this->recordRequestDurationMetric($requestStartedAt, $attributes);
            }

            return $response;
        } catch (Throwable $exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

            throw $exception;
        } finally {
            Tracer::terminateActiveSpansUpToRoot($span);
            $scope->detach();
            $span->end();
        }
    }
}
