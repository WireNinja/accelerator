<?php

namespace WireNinja\Accelerator\Console\Filament;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;
use Throwable;
use WireNinja\Accelerator\Support\Filament\ResourceContextScanner;

/**
 * Hard pass/fail JSON gate for Filament resources.
 *
 * Critical checks only — anti-bullshit anchor for AI agents. Anything
 * "best practice" but not strictly required (form/table split, empty
 * state, page count) belongs in code review, not in a CI gate.
 *
 * Exit codes:
 *   0  PASS   — all critical checks satisfied
 *   1  FAIL   — at least one critical violation
 */
#[Signature('accelerator:verify-resource
    {resource : Resource key (e.g. user) or class FQN}
    {--compact : Compact JSON for piping}')]
#[Description('Hard pass/fail gate: BetterResource trait, DiscoverAsResource attribute, no bulk action leak, policy registered.')]
class VerifyResourceCommand extends Command
{
    public function handle(ResourceContextScanner $scanner): int
    {
        $key = (string) $this->argument('resource');

        try {
            $payload = $scanner->scan(resource: $key);
        } catch (Throwable $throwable) {
            return $this->emit('FAIL', $key, '', [['critical', 'scan', $throwable->getMessage()]]);
        }

        $resource = $payload['resource'] ?? null;

        if (! is_array($resource)) {
            return $this->emit('FAIL', $key, '', [['critical', 'lookup', "Resource [{$key}] not found. Register it in ResourceEnum first."]]);
        }

        $resourceClass = (string) ($resource['class'] ?? '');
        $findings = [];

        // 1. Discovery attribute
        if (empty($resource['discovery']['annotated_as_resource'])) {
            $findings[] = ['critical', 'discovery', 'Missing #[DiscoverAsResource] attribute.'];
        }

        // 2. BetterResource trait
        if (
            $resourceClass !== '' && class_exists($resourceClass)
            && ! in_array('WireNinja\\Accelerator\\Filament\\Traits\\BetterResource', class_uses_recursive($resourceClass), true)
        ) {
            $findings[] = ['critical', 'trait', 'Resource class does not use BetterResource trait.'];
        }

        // 3. Bulk actions ban — scanner already surfaces this in table.violations.
        foreach ($resource['table']['violations'] ?? [] as $violation) {
            $vKey = is_array($violation) ? ($violation['key'] ?? '') : (string) $violation;
            if (is_string($vKey) && str_contains(strtolower($vKey), 'bulk')) {
                $findings[] = ['critical', 'bulk-actions', 'Bulk action API leak (scanner: '.$vKey.'). FILAMENT.md forbids bulk semantics.'];
                break;
            }
        }

        // 4. Policy registered
        if (empty($resource['authorization']['policy']['class'] ?? null)) {
            $findings[] = ['critical', 'policy', 'No policy class registered. Run shield:safe-regenerate.'];
        }

        return $this->emit($findings === [] ? 'PASS' : 'FAIL', $key, $resourceClass, $findings);
    }

    /**
     * @param  list<array{0:string,1:string,2:string}>  $findings
     */
    protected function emit(string $status, string $key, string $class, array $findings): int
    {
        $payload = [
            'status' => $status,
            'resource' => $key,
            'class' => $class,
            'findings' => array_map(
                static fn (array $f): array => ['severity' => $f[0], 'check' => $f[1], 'message' => $f[2]],
                $findings,
            ),
        ];

        try {
            $this->output->writeln(json_encode(
                $payload,
                ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
        } catch (JsonException $exception) {
            $this->output->writeln('{"status":"FAIL","check":"json","message":"'.addslashes($exception->getMessage()).'"}');

            return self::FAILURE;
        }

        return $status === 'PASS' ? self::SUCCESS : self::FAILURE;
    }
}
