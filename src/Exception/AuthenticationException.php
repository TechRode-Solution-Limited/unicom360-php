<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * HTTP 401: the API key is missing, unknown, disabled, expired or used from an IP address the
 * key does not allow. getErrorCode() says which (AUTHENTICATION_FAILED, CREDENTIAL_DISABLED,
 * CLIENT_DISABLED, CREDENTIAL_EXPIRED, IP_NOT_ALLOWED, MISSING_API_KEY, ...). Never retried.
 */
class AuthenticationException extends ApiStatusException
{
    public const TYPE = 'authentication_error';
}
