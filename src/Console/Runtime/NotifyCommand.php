<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Runtime;

use Illuminate\Console\Command;
use JsonException;
use WireNinja\Accelerator\Support\Operations\OperatorTelegramNotifier;

final class NotifyCommand extends Command
{
    protected $signature = 'accelerator:notify:runtime
        {operation : Short operation name}
        {result : started, success, or failed}
        {--context= : Base64-encoded JSON scalar context}
        {--json : Emit JSON}';

    protected $description = 'Internal runtime bridge for Easyploy operator notifications';

    protected $hidden = true;

    public function __construct(private readonly OperatorTelegramNotifier $notifier)
    {
        parent::__construct();
    }

    /** @throws JsonException */
    public function handle(): int
    {
        $operation = trim((string) $this->argument('operation'));
        $result = (string) $this->argument('result');

        if ($operation === '' || ! in_array($result, ['started', 'success', 'failed'], true)) {
            return self::INVALID;
        }

        $delivered = $this->notifier->send(
            $operation,
            $result,
            $this->notificationContext(),
            force: $result !== 'success',
        );
        $payload = json_encode(['status' => 'OK', 'delivered' => $delivered], JSON_THROW_ON_ERROR);

        if ($this->option('json')) {
            $this->line($payload);
        }

        return self::SUCCESS;
    }

    /** @return array<string, scalar|null> */
    private function notificationContext(): array
    {
        $encoded = $this->option('context');

        if (! is_string($encoded) || $encoded === '') {
            return [];
        }

        $decoded = base64_decode($encoded, true);
        $context = is_string($decoded) ? json_decode($decoded, true) : null;

        if (! is_array($context)) {
            return [];
        }

        return array_filter(
            $context,
            static fn (mixed $value, mixed $key): bool => is_string($key) && (is_scalar($value) || $value === null),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
