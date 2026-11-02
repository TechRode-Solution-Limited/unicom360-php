<?php

declare(strict_types=1);

namespace Unicom360\Http;

/**
 * Thrown by a Transport when no complete response arrived.
 *
 * The client turns it into an ApiConnectionException (or ApiTimeoutException when timeout is
 * true), with this exception as getPrevious().
 */
class TransportException extends \RuntimeException
{
    /**
     * @param bool $sent whether any byte of the request may have reached the server; say true
     *                   when unsure
     * @param bool $timeout whether the failure was a timeout (connect or response)
     */
    public function __construct(
        string $message,
        public readonly bool $sent,
        public readonly bool $timeout,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
