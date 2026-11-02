<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * Typed reads from decoded JSON, for building models. A value of the wrong type reads as null.
 *
 * @internal
 */
final class Values
{
    private function __construct() {}

    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * A string, or a number turned into its string form (message_id is always a string).
     *
     * @param array<mixed> $data
     */
    public static function stringish(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_float($value)) {
            return is_finite($value) && floor($value) === $value && abs($value) < 1e15
                ? number_format($value, 0, '.', '')
                : (string) $value;
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (\is_int($value)) {
            return $value;
        }
        if (\is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 9.0e15) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    public static function array(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) ? $value : null;
    }

    /**
     * The entries of a list that are objects (arrays), in order.
     *
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (\is_array($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * The entries of a list that are strings (numbers turned into strings), in order.
     *
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $text = self::stringish(['v' => $item], 'v');
            if ($text !== null) {
                $out[] = $text;
            }
        }

        return $out;
    }
}
