<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use JsonException;
use RuntimeException;

final class InstallJournal
{
    /** @var list<string> */
    private array $completedSteps = [];

    private string $fingerprint;

    private bool $finished = false;

    private readonly string $path;

    /**
     * @throws JsonException
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly InstallPlan $plan,
    ) {
        $this->path = $this->projectRoot.'/.accelerator/install-state.json';
        $this->fingerprint = hash('sha256', json_encode($this->plan->toArray(), JSON_THROW_ON_ERROR));
        $this->load();
    }

    public function start(): void
    {
        $this->persist();
    }

    public function isCompleted(string $step): bool
    {
        return in_array($step, $this->completedSteps, true);
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    /**
     * @throws JsonException
     */
    public function complete(string $step): void
    {
        if (! $this->isCompleted($step)) {
            $this->completedSteps[] = $step;
        }

        $this->persist();
    }

    public function finish(): void
    {
        $this->finished = true;
        $this->persist();
    }

    /**
     * @throws JsonException
     */
    private function load(): void
    {
        if (! is_file($this->path)) {
            return;
        }

        $contents = file_get_contents($this->path);
        $state = is_string($contents) ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR) : null;

        if (! is_array($state) || ($state['fingerprint'] ?? null) !== $this->fingerprint) {
            throw new RuntimeException('An unfinished install exists with different answers. Remove .accelerator/install-state.json to restart.');
        }

        $steps = $state['completed_steps'] ?? [];
        $this->completedSteps = is_array($steps)
            ? array_values(array_filter($steps, is_string(...)))
            : [];
        $this->finished = ($state['finished'] ?? false) === true;
    }

    /**
     * @throws JsonException
     */
    private function persist(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create installation state directory.');
        }

        $payload = json_encode([
            'fingerprint' => $this->fingerprint,
            'plan' => $this->plan->toArray(),
            'completed_steps' => $this->completedSteps,
            'finished' => $this->finished,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        $temporaryPath = $this->path.'.tmp';

        if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false || ! rename($temporaryPath, $this->path)) {
            throw new RuntimeException('Unable to persist installation journal.');
        }
    }
}
