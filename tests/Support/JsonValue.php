<?php

declare(strict_types=1);

namespace Unicom360\Tests\Support;

/**
 * Strict comparison of JSON values decoded with objects as stdClass, so {} and [] differ:
 * the rule requests.json applies to request bodies (deep equality, no extra keys, numbers by
 * value, strings exact).
 */
final class JsonValue
{
    public static function decode(string $text): mixed
    {
        return json_decode($text, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * A fixture vector decoded with objects as stdClass (to keep {} apart from []).
     */
    public static function vector(string $file, string $id): \stdClass
    {
        $text = file_get_contents(Fx::DIR . '/' . $file);
        if ($text === false) {
            throw new \RuntimeException("Cannot read {$file}");
        }
        $decoded = self::decode($text);
        if (!$decoded instanceof \stdClass || !\is_array($decoded->vectors ?? null)) {
            throw new \RuntimeException("{$file} has no vectors");
        }
        foreach ($decoded->vectors as $vector) {
            if ($vector instanceof \stdClass && ($vector->id ?? null) === $id) {
                return $vector;
            }
        }

        throw new \RuntimeException("{$file} has no vector {$id}");
    }

    /**
     * Null when equal, else the JSON path of the first difference.
     */
    public static function difference(mixed $expected, mixed $actual, string $path = ''): ?string
    {
        if ((\is_int($expected) || \is_float($expected)) && (\is_int($actual) || \is_float($actual))) {
            return (float) $expected === (float) $actual ? null : ($path === '' ? '/' : $path);
        }
        if ($expected instanceof \stdClass) {
            if (!$actual instanceof \stdClass) {
                return ($path === '' ? '/' : $path) . ' (expected an object)';
            }
            $e = get_object_vars($expected);
            $a = get_object_vars($actual);
            foreach (array_keys($a) as $key) {
                if (!\array_key_exists($key, $e)) {
                    return "{$path}/{$key} (unexpected key)";
                }
            }
            foreach ($e as $key => $value) {
                if (!\array_key_exists($key, $a)) {
                    return "{$path}/{$key} (missing)";
                }
                $diff = self::difference($value, $a[$key], "{$path}/{$key}");
                if ($diff !== null) {
                    return $diff;
                }
            }

            return null;
        }
        if (\is_array($expected)) {
            if (!\is_array($actual) || \count($actual) !== \count($expected)) {
                return ($path === '' ? '/' : $path) . ' (expected a list of ' . \count($expected) . ')';
            }
            foreach (array_values($expected) as $i => $value) {
                $diff = self::difference($value, array_values($actual)[$i], "{$path}/{$i}");
                if ($diff !== null) {
                    return $diff;
                }
            }

            return null;
        }

        return $expected === $actual ? null : ($path === '' ? '/' : $path);
    }

    /**
     * Whether a JSON Pointer resolves in a decoded document.
     */
    public static function pointerExists(mixed $document, string $pointer): bool
    {
        if ($pointer === '') {
            return true;
        }
        $node = $document;
        foreach (\array_slice(explode('/', $pointer), 1) as $token) {
            $token = str_replace(['~1', '~0'], ['/', '~'], $token);
            if ($node instanceof \stdClass) {
                if (!property_exists($node, $token)) {
                    return false;
                }
                $node = $node->{$token};
            } elseif (\is_array($node) && ctype_digit($token) && \array_key_exists((int) $token, $node)) {
                $node = $node[(int) $token];
            } else {
                return false;
            }
        }

        return true;
    }
}
