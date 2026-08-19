<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Context;

use Illuminate\Support\Str;
use RuntimeException;
use WireNinja\Accelerator\Support\Cast;

final class ResourceRegistry
{
    /** @return array<class-string, list<string>> */
    public function all(): array
    {
        $resources = [];

        foreach (filament()->getPanels() as $panel) {
            foreach ($panel->getResources() as $resource) {
                if (! class_exists($resource)) {
                    continue;
                }

                $resources[$resource] ??= [];
                $resources[$resource][] = $panel->getId();
            }
        }

        ksort($resources);

        return $resources;
    }

    /** @return class-string */
    public function resolve(string $requested): string
    {
        $requested = ltrim(trim($requested), '\\');
        $resources = $this->all();

        if (isset($resources[$requested])) {
            return Cast::mustClassString($requested);
        }

        $normalized = $this->normalize($requested);
        $matches = array_values(array_filter(
            array_keys($resources),
            fn (string $resource): bool => in_array($normalized, [
                $this->normalize($resource),
                $this->normalize(class_basename($resource)),
                $this->normalize(Str::beforeLast(class_basename($resource), 'Resource')),
            ], true),
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException(count($matches) === 0
                ? "Registered Filament resource [{$requested}] was not found."
                : "Resource [{$requested}] is ambiguous: ".implode(', ', $matches));
        }

        return Cast::mustClassString($matches[0]);
    }

    private function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));
    }
}
