<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * The matching rules behind byNumber() and byAddress().
 *
 * @internal
 */
final class Lookup
{
    private function __construct() {}

    /**
     * The first item whose number has the same digits as $wanted (every non-digit removed from
     * both); when $wanted has no digits, the first exact match.
     *
     * @template T
     *
     * @param list<T> $items
     * @param \Closure(T): ?string $number
     *
     * @return T|null
     */
    public static function byNumber(array $items, \Closure $number, string $wanted): mixed
    {
        $digits = self::digits($wanted);
        foreach ($items as $item) {
            $value = $number($item);
            if ($digits === '' ? $value === $wanted : self::digits($value ?? '') === $digits) {
                return $item;
            }
        }

        return null;
    }

    /**
     * The first item whose address equals $wanted after trimming whitespace and lower-casing
     * both.
     *
     * @template T
     *
     * @param list<T> $items
     * @param \Closure(T): ?string $address
     *
     * @return T|null
     */
    public static function byAddress(array $items, \Closure $address, string $wanted): mixed
    {
        $normalised = self::normaliseAddress($wanted);
        foreach ($items as $item) {
            if (self::normaliseAddress($address($item) ?? '') === $normalised) {
                return $item;
            }
        }

        return null;
    }

    private static function digits(string $value): string
    {
        return (string) preg_replace('/[^0-9]/', '', $value);
    }

    private static function normaliseAddress(string $value): string
    {
        $trimmed = (string) preg_replace('/\A[\s\x{00A0}\x{FEFF}]+|[\s\x{00A0}\x{FEFF}]+\z/u', '', $value);

        return \function_exists('mb_strtolower') ? mb_strtolower($trimmed, 'UTF-8') : strtolower($trimmed);
    }
}
