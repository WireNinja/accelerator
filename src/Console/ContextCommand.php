<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;
use WireNinja\Accelerator\Support\Context\CompactModelScanner;
use WireNinja\Accelerator\Support\Context\CompactResourceScanner;
use WireNinja\Accelerator\Support\Context\ResourceRegistry;

/**
 * The `resource` subject is a legacy compatibility path. Inspect registered
 * Filament resources and their policies directly instead. Model and list
 * context remain supported.
 */
final class ContextCommand extends Command
{
    /** @var string */
    protected $signature = 'accelerator:context
        {subject : model, resource, or list}
        {target? : Entity name, or models/resources for list}
        {--pretty : Pretty-print JSON}
        {--expand : Include explicitly requested deep details}';

    /** @var string */
    protected $description = 'Emit compact, source-backed Accelerator context as versioned JSON';

    public function handle(
        CompactModelScanner $models,
        CompactResourceScanner $resources,
        ResourceRegistry $registry,
    ): int {
        $subject = strtolower((string) $this->argument('subject'));
        $target = trim((string) $this->argument('target'));

        try {
            $payload = match ($subject) {
                'model' => $target === ''
                    ? throw new InvalidArgumentException('Model name is required.')
                    : $models->scan($target, (bool) $this->option('expand')),
                'resource' => $target === ''
                    ? throw new InvalidArgumentException('Resource name is required.')
                    : $resources->scan($target, (bool) $this->option('expand')),
                'list' => $this->listing($target, $models, $registry),
                default => throw new InvalidArgumentException('Subject must be model, resource, or list.'),
            };

            $json = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                    | ($this->option('pretty') ? JSON_PRETTY_PRINT : 0),
            );
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->output->writeln($json);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function listing(string $target, CompactModelScanner $models, ResourceRegistry $registry): array
    {
        return match ($target) {
            'models' => ['schema' => 1, 'type' => 'model-list', 'models' => $models->all()],
            'resources' => [
                'schema' => 1,
                'type' => 'resource-list',
                'resources' => array_map(
                    static fn (array $panels, string $class): array => ['class' => $class, 'panels' => $panels],
                    $registry->all(),
                    array_keys($registry->all()),
                ),
            ],
            default => throw new InvalidArgumentException('List target must be models or resources.'),
        };
    }
}
