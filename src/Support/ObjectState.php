<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support;

use WeakMap;

/**
 * Per-instance state storage for values cached by macro closures registered via
 * `Macroable::macro()`, since macros are static closures with no instance
 * properties of their own to cache into (unlike the Yii2 Behavior objects they replace).
 */
final class ObjectState
{
    private static WeakMap $state;

    public static function has(object $object, string $key): bool
    {
        return array_key_exists($key, self::all($object));
    }

    public static function get(object $object, string $key, mixed $default = null): mixed
    {
        return self::all($object)[$key] ?? $default;
    }

    public static function set(object $object, string $key, mixed $value): void
    {
        $data = self::all($object);
        $data[$key] = $value;
        self::$state ??= new WeakMap();
        self::$state[$object] = $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function all(object $object): array
    {
        return (self::$state ??= new WeakMap())[$object] ?? [];
    }
}
