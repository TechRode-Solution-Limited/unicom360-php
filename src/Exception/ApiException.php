<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * A business refusal: HTTP 200 with "success": false (or "status": false on the balance
 * endpoints). Nothing was sent.
 *
 * getErrorCode() gives the normalised code. Subclasses exist for the codes callers usually
 * handle: InsufficientBundleException, DuplicateReferenceException,
 * NoValidRecipientsException and SessionWindowClosedException.
 */
class ApiException extends Unicom360Exception
{
    use ResponseDetails;

    public const TYPE = 'api_error';

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
