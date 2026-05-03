<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support;

use Illuminate\Support\Facades\File;
use ReflectionClass;

final class PhpDocPropertyTypeResolver
{
    /**
     * @return array<string, array{raw: string, normalized: array<int, string>}>
     */
    public static function resolve(ReflectionClass $reflection): array
    {
        $docComment = $reflection->getDocComment() ?: '';

        if ($docComment === '') {
            return [];
        }

        preg_match_all(
            '/@property(?:-read|-write)?\s+([^\s]+)\s+\$([A-Za-z_][A-Za-z0-9_]*)/',
            $docComment,
            $matches,
            PREG_SET_ORDER,
        );

        $imports = self::resolveImports($reflection);
        $properties = [];

        foreach ($matches as $match) {
            $rawType = trim($match[1]);
            $property = trim($match[2]);

            $properties[$property] = [
                'raw' => $rawType,
                'normalized' => self::normalizeUnionTypes($rawType, $reflection, $imports),
            ];
        }

        return $properties;
    }

    /**
     * @return array<string, string>
     */
    private static function resolveImports(ReflectionClass $reflection): array
    {
        $filePath = $reflection->getFileName();

        if (($filePath === false) || (! File::exists($filePath))) {
            return [];
        }

        preg_match_all('/^use\s+([^;]+);$/m', File::get($filePath), $matches);

        $imports = [];

        foreach ($matches[1] ?? [] as $import) {
            $import = trim($import);

            if (
                str_starts_with($import, 'function ')
                || str_starts_with($import, 'const ')
                || str_contains($import, '{')
            ) {
                continue;
            }

            [$className, $alias] = array_pad(preg_split('/\s+as\s+/i', $import) ?: [$import], 2, null);
            $className = ltrim(trim($className), '\\');
            $alias ??= class_basename($className);

            $imports[trim($alias)] = $className;
        }

        return $imports;
    }

    /**
     * @param  array<string, string>  $imports
     * @return array<int, string>
     */
    private static function normalizeUnionTypes(string $rawType, ReflectionClass $reflection, array $imports): array
    {
        $normalized = [];

        foreach (explode('|', $rawType) as $type) {
            $type = trim($type);

            if ($type === '') {
                continue;
            }

            if (str_starts_with($type, '?')) {
                $normalized[] = 'null';
                $type = ltrim($type, '?');
            }

            if ($type === '') {
                continue;
            }

            $normalized[] = self::normalizeSingleType($type, $reflection, $imports);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<string, string>  $imports
     */
    private static function normalizeSingleType(string $type, ReflectionClass $reflection, array $imports): string
    {
        if (str_ends_with($type, '[]')) {
            return 'array';
        }

        if (str_contains($type, '<')) {
            $type = strstr($type, '<', true) ?: $type;
        }

        $normalizedType = strtolower($type);

        return match ($normalizedType) {
            'array' => 'array',
            'bool', 'boolean', 'false', 'true' => 'bool',
            'float', 'double' => 'float',
            'int', 'integer' => 'int',
            'mixed' => 'mixed',
            'null' => 'null',
            'object' => 'object',
            'string' => 'string',
            '$this', 'self', 'static' => $reflection->getName(),
            'parent' => get_parent_class($reflection->getName()) ?: $reflection->getName(),
            default => self::resolveClassLikeType($type, $reflection, $imports),
        };
    }

    /**
     * @param  array<string, string>  $imports
     */
    private static function resolveClassLikeType(string $type, ReflectionClass $reflection, array $imports): string
    {
        if (str_starts_with($type, '\\')) {
            return ltrim($type, '\\');
        }

        if (array_key_exists($type, $imports)) {
            return $imports[$type];
        }

        $namespace = $reflection->getNamespaceName();

        return blank($namespace) ? $type : $namespace.'\\'.$type;
    }
}
