<?php

declare(strict_types=1);

namespace Unicom360\Tests\Support;

/**
 * Typed access to decoded fixture JSON. Every accessor throws when the fixture does not have
 * the expected shape, so a malformed or unexpected vector fails loudly instead of being skipped.
 */
final class Fx
{
    public const DIR = __DIR__ . '/../conformance';

    /** @var array<string, array<mixed>> */
    private static array $files = [];

    /**
     * A fixture file decoded with objects as arrays.
     *
     * @return array<mixed>
     */
    public static function file(string $name): array
    {
        if (!isset(self::$files[$name])) {
            $text = file_get_contents(self::DIR . '/' . $name);
            if ($text === false) {
                throw new \RuntimeException("Cannot read fixture {$name}");
            }
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            if (!\is_array($decoded)) {
                throw new \RuntimeException("Fixture {$name} is not a JSON object");
            }
            self::$files[$name] = $decoded;
        }

        return self::$files[$name];
    }

    /**
     * The vectors of a fixture file, keyed by id (for PHPUnit data providers).
     *
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(string $name): array
    {
        $out = [];
        foreach (self::list(self::file($name), 'vectors') as $vector) {
            $vector = self::asArray($vector, 'vector');
            $id = self::str($vector, 'id');
            if (isset($out[$id])) {
                throw new \RuntimeException("Duplicate vector id {$id} in {$name}");
            }
            $out[$id] = [$vector];
        }
        if ($out === []) {
            throw new \RuntimeException("Fixture {$name} has no vectors");
        }

        return $out;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function arr(array $data, string|int $key): array
    {
        if (!\array_key_exists($key, $data)) {
            throw new \RuntimeException("Fixture key {$key} is missing");
        }

        return self::asArray($data[$key], (string) $key);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    public static function optArr(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : self::asArray($value, $key);
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     */
    public static function list(array $data, string $key): array
    {
        $value = self::arr($data, $key);
        if (!array_is_list($value)) {
            throw new \RuntimeException("Fixture key {$key} is not a list");
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $out = [];
        foreach (self::list($data, $key) as $value) {
            if (!\is_string($value)) {
                throw new \RuntimeException("Fixture key {$key} holds a non-string");
            }
            $out[] = $value;
        }

        return $out;
    }

    /**
     * @param array<mixed> $data
     */
    public static function str(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value)) {
            throw new \RuntimeException("Fixture key {$key} is not a string");
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function optStr(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw new \RuntimeException("Fixture key {$key} is not a string");
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!\is_int($value)) {
            throw new \RuntimeException("Fixture key {$key} is not an integer");
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function num(array $data, string $key): float
    {
        $value = $data[$key] ?? null;
        if (!\is_int($value) && !\is_float($value)) {
            throw new \RuntimeException("Fixture key {$key} is not a number");
        }

        return (float) $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function optNum(array $data, string $key): ?float
    {
        return isset($data[$key]) ? self::num($data, $key) : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!\is_bool($value)) {
            throw new \RuntimeException("Fixture key {$key} is not a boolean");
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return string|list<string>
     */
    public static function strOrList(array $data, string $key): string|array
    {
        $value = $data[$key] ?? null;
        if (\is_string($value)) {
            return $value;
        }

        return self::strings($data, $key);
    }

    /**
     * @return array<mixed>
     */
    public static function asArray(mixed $value, string $what): array
    {
        if (!\is_array($value)) {
            throw new \RuntimeException("Fixture value {$what} is not an object or list");
        }

        return $value;
    }

    /**
     * Fails when a vector carries a key the harness does not understand.
     *
     * @param array<mixed> $vector
     * @param list<string> $known
     */
    public static function assertKnownKeys(array $vector, array $known, string $where): void
    {
        $unknown = array_diff(array_map('strval', array_keys($vector)), $known);
        if ($unknown !== []) {
            throw new \RuntimeException("{$where} has keys this harness does not understand: " . implode(', ', $unknown));
        }
    }
}
