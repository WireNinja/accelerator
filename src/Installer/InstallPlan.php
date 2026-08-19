<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use WireNinja\Accelerator\Configuration\FeatureRegistry;
use WireNinja\Accelerator\Support\Cast;

final readonly class InstallPlan
{
    /**
     * @param  list<string>  $features
     * @param  array<string, array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}>  $reverbApplications
     */
    private function __construct(
        public string $appName,
        public string $appUrl,
        public string $adminName,
        public string $adminUsername,
        public string $adminEmail,
        public string $adminPasswordHash,
        public string $packageManager,
        public string $database,
        public bool $useRedis,
        public array $features,
        public array $reverbApplications,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Cast::stringKeyedArray(get_object_vars($this));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $input = [
            'appName' => $data['appName'] ?? null,
            'appUrl' => $data['appUrl'] ?? null,
            'adminName' => $data['adminName'] ?? null,
            'adminUsername' => $data['adminUsername'] ?? null,
            'adminEmail' => $data['adminEmail'] ?? null,
            'adminPasswordHash' => $data['adminPasswordHash'] ?? null,
            'packageManager' => $data['packageManager'] ?? 'pnpm',
            'database' => $data['database'] ?? null,
            'useRedis' => $data['useRedis'] ?? false,
            'features' => $data['features'] ?? [],
            'reverbApplications' => $data['reverbApplications'] ?? [],
        ];
        $usesRealtime = is_array($input['features']) && in_array('realtime', $input['features'], true);

        try {
            Validator::make($input, [
                'appName' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
                'appUrl' => ['required', 'url:http,https'],
                'adminName' => ['required', 'string', 'max:100'],
                'adminUsername' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
                'adminEmail' => ['required', 'email'],
                'adminPasswordHash' => [
                    'required',
                    'string',
                    static function (string $attribute, mixed $value, Closure $fail): void {
                        if (! is_string($value) || ! password_get_info($value)['algo']) {
                            $fail('The Super Admin password hash is invalid.');
                        }
                    },
                ],
                'packageManager' => ['required', Rule::in(['pnpm', 'npm'])],
                'database' => ['required', Rule::in(['sqlite', 'mysql', 'pgsql'])],
                'useRedis' => ['required', 'boolean'],
                'features' => ['array'],
                'features.*' => ['string', 'distinct', Rule::in(FeatureRegistry::names())],
                'reverbApplications' => ['array'],
                'reverbApplications.local' => [Rule::requiredIf($usesRealtime), 'array'],
                'reverbApplications.local.name' => [Rule::requiredIf($usesRealtime), 'string'],
                'reverbApplications.local.app_id' => [Rule::requiredIf($usesRealtime), 'string'],
                'reverbApplications.local.key' => [Rule::requiredIf($usesRealtime), 'string'],
                'reverbApplications.local.secret' => [Rule::requiredIf($usesRealtime), 'string'],
                'reverbApplications.local.allowed_origins' => [Rule::requiredIf($usesRealtime), 'array', 'min:1'],
                'reverbApplications.local.allowed_origins.*' => ['url:http,https'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new InvalidArgumentException($exception->validator->errors()->first(), previous: $exception);
        }

        return new self(
            appName: self::string($input, 'appName'),
            appUrl: self::string($input, 'appUrl'),
            adminName: self::string($input, 'adminName'),
            adminUsername: self::string($input, 'adminUsername'),
            adminEmail: self::string($input, 'adminEmail'),
            adminPasswordHash: self::string($input, 'adminPasswordHash'),
            packageManager: self::string($input, 'packageManager'),
            database: self::string($input, 'database'),
            useRedis: (bool) $input['useRedis'],
            features: Cast::stringList($input['features']),
            reverbApplications: self::reverbApplications($input['reverbApplications']),
        );
    }

    /** @return array<string, array{name: string, app_id: string, key: string, secret: string, allowed_origins: list<string>}> */
    private static function reverbApplications(mixed $value): array
    {
        return array_map(
            static function (mixed $application): array {
                $values = Cast::stringKeyedArray($application);

                return [
                    'name' => Cast::mustString($values['name'] ?? null),
                    'app_id' => Cast::mustString($values['app_id'] ?? null),
                    'key' => Cast::mustString($values['key'] ?? null),
                    'secret' => Cast::mustString($values['secret'] ?? null),
                    'allowed_origins' => Cast::stringList($values['allowed_origins'] ?? null),
                ];
            },
            Cast::stringKeyedArray($value),
        );
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : '';
    }
}
