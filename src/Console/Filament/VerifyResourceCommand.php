<?php

namespace WireNinja\Accelerator\Console\Filament;

use Filament\Resources\Resource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use JsonException;
use Throwable;
use WireNinja\Accelerator\Support\Context\ResourceRegistry;

/**
 * Legacy compatibility command. Native Filament inspection, static analysis,
 * and authenticated UI verification are authoritative for resources.
 */
#[Signature('accelerator:verify-resource
    {resource : Resource key (e.g. user) or class FQN}
    {--compact : Compact JSON for piping}')]
#[Description('Verify hard Filament registration, model, and policy invariants.')]
class VerifyResourceCommand extends Command
{
    public function handle(ResourceRegistry $registry): int
    {
        $key = (string) $this->argument('resource');

        try {
            $resourceClass = $registry->resolve($key);
        } catch (Throwable $throwable) {
            return $this->emit('FAIL', $key, '', [['critical', 'lookup', $throwable->getMessage()]]);
        }

        $findings = [];

        if (! is_subclass_of($resourceClass, Resource::class)) {
            $findings[] = ['critical', 'resource', 'Registered class is not a Filament resource.'];
        }

        $model = is_subclass_of($resourceClass, Resource::class) ? $resourceClass::getModel() : null;

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            $findings[] = ['critical', 'model', 'Resource does not resolve an Eloquent model.'];
        } elseif (Gate::getPolicyFor($model) === null) {
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
