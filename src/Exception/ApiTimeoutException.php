<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * The connection or the response timed out. A connect timeout is always retried; a response
 * timeout only when the operation is retry-safe, because the gateway may have acted on it.
 */
class ApiTimeoutException extends ApiConnectionException
{
    public const TYPE = 'timeout_error';
}
