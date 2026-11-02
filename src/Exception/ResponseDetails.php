<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * The fields shared by errors built from an HTTP response: the normalised code, the envelope's
 * data and the response headers.
 *
 * @internal
 */
trait ResponseDetails
{
    private string $errorCode = 'UNKNOWN';

    private mixed $data = null;

    /** @var array<string, string> */
    private array $headers = [];

    /**
     * The normalised error code (see Unicom360\ErrorCode), for example INSUFFICIENT_BUNDLE,
     * AUTHENTICATION_FAILED or UNKNOWN.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * The envelope's data as decoded JSON (arrays for objects), or null when the body had none.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * The response headers, names lower-cased, repeated headers joined with ", ".
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private function initResponseDetails(string $errorCode, mixed $data, array $headers): void
    {
        $this->errorCode = $errorCode;
        $this->data = $data;
        $this->headers = $headers;
    }
}
