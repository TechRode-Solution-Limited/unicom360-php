<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * Reads an environment variable from getenv(), $_ENV or $_SERVER (frameworks that load .env
 * files fill one of the last two).
 *
 * @internal
 */
final class Env
{
    private function __construct() {}

    public static function get(string $name): ?string
    {
        $value = getenv($name);
        if (\is_string($value)) {
            return $value;
        }
        foreach ([$_ENV, $_SERVER] as $source) {
            if (isset($source[$name]) && \is_string($source[$name])) {
                return $source[$name];
            }
        }

        return null;
    }
}
