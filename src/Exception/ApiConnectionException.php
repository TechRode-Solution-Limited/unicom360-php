<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * The request did not complete: DNS failure, refused connection, TLS failure, or a connection
 * reset after the request was written. getPrevious() holds the underlying error.
 *
 * Failures before anything was sent are always retried. Failures after the request was written
 * are retried only when the operation is retry-safe.
 */
class ApiConnectionException extends Unicom360Exception
{
    public const TYPE = 'connection_error';
}
