<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Configuration;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Support\Cast;

final readonly class ReverbApplicationRegistry
{
    public const PATH = '.accelerator/reverb-apps.json';

    public function __construct(private string $projectRoot) {}

    /**
     * @return array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}
     */
    public static function generate(string $name, string $origin): array
    {
        return [
            'name' => $name,
            'app_id' => (string) random_int(100_000, 999_999),
            'key' => Str::lower(Str::random(20)),
            'secret' => Str::lower(Str::random(32)),
            'allowed_origins' => [self::originHost($origin)],
        ];
    }

    /**
     * @param  array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}  $application
     *
     * @throws JsonException
     */
    public function upsert(array $application): void
    {
        $applications = $this->applications();
        $applications[$application['name']] = $application;

        $this->write(array_values($applications));
    }

    /**
     * @return array<string, array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}>
     *
     * @throws JsonException
     */
    public function applications(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($document) || ($document['schema'] ?? null) !== 1 || ! is_array($document['applications'] ?? null)) {
            throw new RuntimeException('The Accelerator Reverb application registry is invalid.');
        }

        $applications = [];

        foreach ($document['applications'] as $application) {
            if (! is_array($application)) {
                throw new RuntimeException('The Accelerator Reverb application registry contains an invalid entry.');
            }

            $normalized = $this->normalize($application);
            $applications[$normalized['name']] = $normalized;
        }

        return $applications;
    }

    /**
     * @param  list<array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}>  $applications
     *
     * @throws JsonException
     */
    public function write(array $applications): void
    {
        $path = $this->path();
        is_dir(dirname($path)) || mkdir(dirname($path), 0700, true);
        $contents = json_encode([
            'schema' => 1,
            'applications' => $applications,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the Accelerator Reverb application registry.');
        }

        chmod($path, 0600);
    }

    private function path(): string
    {
        return rtrim($this->projectRoot, '/').'/'.self::PATH;
    }

    /**
     * @param  array<array-key, mixed>  $application
     * @return array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}
     */
    private function normalize(array $application): array
    {
        $name = is_string($application['name'] ?? null) ? trim($application['name']) : '';
        $appId = is_string($application['app_id'] ?? null) ? trim($application['app_id']) : '';
        $key = is_string($application['key'] ?? null) ? trim($application['key']) : '';
        $secret = is_string($application['secret'] ?? null) ? trim($application['secret']) : '';
        $origins = Cast::stringList($application['allowed_origins'] ?? null);

        if ($name === '' || $appId === '' || $key === '' || $secret === '' || $origins === []) {
            throw new RuntimeException('Every Reverb application requires a name, app ID, key, secret, and allowed origin.');
        }

        return [
            'name' => $name,
            'app_id' => $appId,
            'key' => $key,
            'secret' => $secret,
            'allowed_origins' => $origins,
        ];
    }

    private static function originHost(string $origin): string
    {
        $host = parse_url($origin, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new RuntimeException("Invalid Reverb application origin [{$origin}].");
        }

        $port = parse_url($origin, PHP_URL_PORT);

        return is_int($port) ? "{$host}:{$port}" : $host;
    }
}
