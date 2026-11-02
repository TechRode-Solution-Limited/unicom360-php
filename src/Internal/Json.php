<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * JSON helpers.
 *
 * @internal
 */
final class Json
{
    private function __construct() {}

    /**
     * Decodes a body that must be a JSON object. Returns null for an empty body, invalid JSON
     * or any JSON value that is not an object (an array, a string, a number).
     *
     * Objects become associative arrays.
     *
     * @return array<mixed>|null
     */
    public static function decodeObject(string $text): ?array
    {
        if ($text === '') {
            return null;
        }
        try {
            $value = json_decode($text, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!$value instanceof \stdClass) {
            return null;
        }
        $array = self::toArray($value);

        return \is_array($array) ? $array : null;
    }

    /**
     * Turns decoded JSON (stdClass objects) into arrays, recursively.
     */
    public static function toArray(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::toArray($item);
            }
        }

        return $value;
    }

    /**
     * Resolves JsonSerializable values (the SDK's builders) into plain arrays, stdClass objects
     * and scalars, recursively, so a request body can be inspected before it is encoded.
     */
    public static function normalize(mixed $value): mixed
    {
        while ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalize($item);
            }
        } elseif ($value instanceof \stdClass) {
            $copy = new \stdClass();
            foreach (get_object_vars($value) as $key => $item) {
                $copy->{$key} = self::normalize($item);
            }
            $value = $copy;
        }

        return $value;
    }

    /**
     * Encodes a request body. An empty array is sent as {} (every body is a JSON object).
     *
     * @param array<mixed> $body
     */
    public static function encodeBody(array $body): string
    {
        return json_encode(
            $body === [] ? new \stdClass() : $body,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
