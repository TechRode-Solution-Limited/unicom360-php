<?php

declare(strict_types=1);

namespace Unicom360\Internal;

use Unicom360\Exception\Unicom360Exception;

/**
 * The outcome of one HTTP attempt.
 *
 * @internal
 */
final class Attempt
{
    /**
     * @param array<mixed>|null $result
     */
    private function __construct(
        public readonly ?array $result,
        public readonly ?Unicom360Exception $error,
        public readonly bool $retryable,
        public readonly string $cause,
        public readonly ?float $rateLimitedAt,
    ) {}

    /**
     * @param array<mixed> $result
     */
    public static function success(array $result): self
    {
        return new self($result, null, false, '', null);
    }

    public static function failure(Unicom360Exception $error, bool $retryable, string $cause, ?float $rateLimitedAt = null): self
    {
        return new self(null, $error, $retryable, $cause, $rateLimitedAt);
    }
}
