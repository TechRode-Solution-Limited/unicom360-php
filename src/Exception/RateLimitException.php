<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * HTTP 429: the API key is over its requests-per-minute limit.
 *
 * The limit is a fixed UTC-minute window and the gateway sends no Retry-After header, so
 * getRetryAfter() is the number of seconds until the next UTC minute, computed when the
 * response arrived. The request was rejected before any processing, so a 429 is always retried
 * (up to maxRetries) when the wait fits in maxRetryWait.
 */
class RateLimitException extends ApiStatusException
{
    public const TYPE = 'rate_limit_error';

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message,
        int $httpStatus,
        ?string $apiReference,
        string $rawBody,
        string $errorCode = 'UNKNOWN',
        mixed $data = null,
        array $headers = [],
        private readonly float $retryAfter = 60.0,
    ) {
        parent::__construct($message, $httpStatus, $apiReference, $rawBody, $errorCode, $data, $headers);
    }

    /** Seconds until the next UTC minute, in (0, 60]. */
    public function getRetryAfter(): float
    {
        return $this->retryAfter;
    }
}
