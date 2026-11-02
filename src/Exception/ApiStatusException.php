<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * Any non-2xx response, or a 2xx response without a usable envelope.
 *
 * Subclasses: AuthenticationException (401), RateLimitException (429),
 * InvalidRequestException (400) and ApiServerException (5xx). Other statuses (3xx, 403, 404,
 * 406, ...) are raised as this class itself. Redirects are never followed.
 */
class ApiStatusException extends Unicom360Exception
{
    use ResponseDetails;

    public const TYPE = 'api_status_error';

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
    ) {
        parent::__construct($message, $httpStatus, $apiReference, $rawBody);
        $this->initResponseDetails($errorCode, $data, $headers);
    }
}
