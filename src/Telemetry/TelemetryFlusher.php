<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use RuntimeException;
use Throwable;

final class TelemetryFlusher
{
    private bool $flushing = false;

    public function __construct(
        private readonly TelemetryBuffer $buffer,
        private readonly TelemetryStore $store,
        private readonly TelemetryNotifier $notifier,
    ) {}

    public function flush(): void
    {
        if ($this->flushing) {
            return;
        }

        $this->flushing = true;

        try {
            if ($this->buffer->hasRows()) {
                $snapshot = $this->buffer->snapshot();
                $result = $this->store->persist($snapshot, $this->notifier->channels());

                if ($result === null) {
                    return;
                }

                $acknowledged = $this->buffer->acknowledge($result['acknowledged']);

                if ($acknowledged !== count($result['acknowledged'])) {
                    throw new RuntimeException('Telemetry committed a snapshot but could not acknowledge every unchanged buffer row. Idempotent retry will continue.');
                }

                $this->buffer->recordFlush($result['persisted'], $result['malformed']);
            }
        } catch (Throwable $exception) {
            $this->store->disconnect();
            $this->buffer->recordFlushFailure($exception);
            rescue(fn () => logger()->warning('[Telemetry] Flush failed; unacknowledged rows remain retryable.', [
                'error' => SensitiveDataFilter::text($exception->getMessage(), 500),
            ]));

            return;
        } finally {
            $this->flushing = false;
        }

        try {
            $this->notifier->deliverPending($this->store, $this->buffer);
        } catch (Throwable $exception) {
            $this->store->disconnect();
            $this->buffer->recordNotificationFailure($exception);
            rescue(fn () => logger()->warning('[Telemetry] Notification delivery pass failed.', [
                'error' => SensitiveDataFilter::text($exception->getMessage(), 500),
            ]));
        }
    }
}
