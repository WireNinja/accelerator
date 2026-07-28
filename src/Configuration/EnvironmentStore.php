<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Configuration;

use RuntimeException;

final readonly class EnvironmentStore
{
    public function __construct(private string $projectRoot) {}

    /**
     * @return array<string, string>
     */
    public function read(string $relativePath): array
    {
        $path = $this->path($relativePath);

        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if (! is_array($lines)) {
            throw new RuntimeException("Unable to read [{$relativePath}].");
        }

        $values = [];

        foreach ($lines as $number => $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                throw new RuntimeException(sprintf('Invalid environment line %d in [%s].', $number + 1, $relativePath));
            }

            [$key, $value] = array_map(trim(...), explode('=', $line, 2));

            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                throw new RuntimeException(sprintf('Invalid environment key on line %d in [%s].', $number + 1, $relativePath));
            }

            if (array_key_exists($key, $values)) {
                throw new RuntimeException("Duplicate environment key [{$key}] in [{$relativePath}].");
            }

            $values[$key] = $this->unquote($value);
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    public function merge(string $relativePath, array $values, bool $create = false): void
    {
        $path = $this->path($relativePath);

        if (! is_file($path) && ! $create) {
            throw new RuntimeException("Missing environment file [{$relativePath}].");
        }

        $contents = is_file($path) ? file_get_contents($path) : '';

        if (! is_string($contents)) {
            throw new RuntimeException("Unable to read [{$relativePath}].");
        }

        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1 || str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new RuntimeException("Invalid environment value for [{$key}].");
            }

            $line = $key . '=' . $this->quote($value);
            $pattern = '/^(?:#\s*)?' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents, 1);
            } else {
                $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
            }
        }

        $this->write($relativePath, rtrim($contents) . PHP_EOL);
    }

    public function write(string $relativePath, string $contents): void
    {
        $path = $this->path($relativePath);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory for [{$relativePath}].");
        }

        $temporaryPath = tempnam($directory, '.accelerator-');

        if (! is_string($temporaryPath)) {
            throw new RuntimeException("Unable to allocate temporary file for [{$relativePath}].");
        }

        try {
            if (
                file_put_contents($temporaryPath, $contents, LOCK_EX) === false
                || ! chmod($temporaryPath, 0600)
                || ! rename($temporaryPath, $path)
            ) {
                throw new RuntimeException("Unable to atomically write [{$relativePath}].");
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @param array<string, string> $values */
    public function replace(string $relativePath, array $values): void
    {
        $lines = [];

        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1 || str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new RuntimeException("Invalid environment value for [{$key}].");
            }

            $lines[] = $key . '=' . $this->quote($value);
        }

        $this->write($relativePath, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function path(string $relativePath): string
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
            throw new RuntimeException("Unsafe environment path [{$relativePath}].");
        }

        return rtrim($this->projectRoot, '/') . '/' . $relativePath;
    }

    private function quote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_:\/.@+\-]+$/', $value) === 1) {
            return $value;
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function unquote(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        if ($value[0] === '"' && str_ends_with($value, '"')) {
            return stripcslashes(substr($value, 1, -1));
        }

        if ($value[0] === "'" && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
