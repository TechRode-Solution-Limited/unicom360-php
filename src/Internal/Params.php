<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * Builds a request body from an array keyed by API names and/or named arguments.
 *
 * @internal
 */
final class Params
{
    private function __construct() {}

    /**
     * Starts from $params (API names; entries set to null are dropped) and adds every named
     * argument that is not null, which wins over the array.
     *
     * @param array<mixed> $params
     * @param array<string, mixed> $named API name to value
     *
     * @return array<mixed>
     */
    public static function merge(array $params, array $named): array
    {
        $body = [];
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $body[$key] = $value;
            }
        }
        foreach ($named as $key => $value) {
            if ($value !== null) {
                $body[$key] = $value;
            }
        }

        return $body;
    }
}
