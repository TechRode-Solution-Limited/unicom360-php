<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * The base class of every error the SDK raises.
 *
 * Catch this to handle any Unicom360 failure in one place. The message never contains the API
 * key or a webhook secret.
 */
class Unicom360Exception extends \RuntimeException
{
    /**
     * The error's type string (see getType()); every subclass redefines it.
     *
     * @var string
     */
    public const TYPE = 'unicom360_error';

    private int $attempts = 1;

    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly ?string $apiReference = null,
        private readonly ?string $rawBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The error's type string: configuration_error, connection_error, timeout_error,
     * api_status_error, authentication_error, rate_limit_error, invalid_request_error,
     * api_server_error, api_error or signature_verification_error.
     */
    public function getType(): string
    {
        return static::TYPE;
    }

    /** The HTTP status of the response that caused the error, or null when there was none. */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /** The gateway's api_reference for the request, when the response carried one. Quote it to support. */
    public function getApiReference(): ?string
    {
        return $this->apiReference;
    }

    /** The response body text, or null when there was no response. */
    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }

    /** The number of HTTP attempts the operation made, retries included (1 when none). */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * @internal set by the client once the retry loop gives up
     */
    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }
}
