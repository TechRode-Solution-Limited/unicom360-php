<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * HTTP 400: the request has the wrong shape (a missing field, a wrong type, a value out of
 * range). The message is the gateway's explanation. Nothing was sent. Never retried.
 */
class InvalidRequestException extends ApiStatusException
{
    public const TYPE = 'invalid_request_error';
}
