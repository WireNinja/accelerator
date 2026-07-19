<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Throwable;

final class TelemetryRecorder
{
    public function __construct(
        private readonly TelemetryBuffer $buffer,
    ) {}

    public function capture(Throwable $exception, ?Request $request): bool
    {
        $user = $request?->user();

        if (! $user instanceof Authenticatable || ! $this->sampled()) {
            return false;
        }

        $description = ExceptionFingerprint::describe($exception, $request);
        $context = $this->requestContext($request);

        return $this->buffer->push([
            ...$description,
            'message' => SensitiveDataFilter::text($exception->getMessage(), 4000),
            'stack_trace' => SensitiveDataFilter::text($exception->getTraceAsString(), 32000),
            'user_id' => (string) $user->getAuthIdentifier(),
            'user_label' => $this->userLabel($user),
            'url' => $this->routeUrl($request),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'duration_ms' => $this->duration($request),
            'context' => $context === [] ? null : $context,
            'occurred_at' => now()->toISOString(),
        ]);
    }

    private function sampled(): bool
    {
        $rate = max(0, min(100, (int) config('accelerator.telemetry.sample_rate', 100)));

        return $rate === 100 || ($rate > 0 && random_int(1, 100) <= $rate);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(Request $request): array
    {
        $context = [];

        if (config('accelerator.telemetry.capture_query', false)) {
            $context['query'] = SensitiveDataFilter::parameters($request->query());
        }

        if (config('accelerator.telemetry.capture_headers', false)) {
            $context['headers'] = SensitiveDataFilter::headers($request->headers->all());
        }

        if (config('accelerator.telemetry.capture_payload', false)) {
            $context['payload'] = SensitiveDataFilter::parameters($request->all());
        }

        return $context;
    }

    private function userLabel(Authenticatable $user): ?string
    {
        foreach (['name', 'username', 'email'] as $attribute) {
            $value = data_get($user, $attribute);

            if (is_string($value) && trim($value) !== '') {
                return SensitiveDataFilter::text(trim($value), 255);
            }
        }

        return null;
    }

    private function duration(Request $request): ?float
    {
        $startedAt = $request->server('REQUEST_TIME_FLOAT');

        if (! is_numeric($startedAt)) {
            return null;
        }

        return round((microtime(true) - (float) $startedAt) * 1000, 2);
    }

    private function routeUrl(Request $request): string
    {
        $routeUri = $request->route()?->uri();

        if (! is_string($routeUri) || $routeUri === '') {
            return $request->root();
        }

        return rtrim($request->root(), '/').'/'.ltrim($routeUri, '/');
    }
}
