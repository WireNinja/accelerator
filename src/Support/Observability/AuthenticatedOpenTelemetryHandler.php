<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Observability;

use Keepsuit\LaravelOpenTelemetry\Support\OpenTelemetryMonologHandler;
use Monolog\LogRecord;

final class AuthenticatedOpenTelemetryHandler extends OpenTelemetryMonologHandler
{
    public function isHandling(LogRecord $record): bool
    {
        if (! config('accelerator.features.observability') || ! parent::isHandling($record)) {
            return false;
        }

        if (app()->runningInConsole()) {
            return true;
        }

        return request()->user() !== null;
    }
}
