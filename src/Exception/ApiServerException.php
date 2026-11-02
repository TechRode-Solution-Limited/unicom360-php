<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * HTTP 500 to 599, from the gateway or a proxy in front of it. Retried only for read
 * operations and for sends every message of which carries a user_reference_id.
 */
class ApiServerException extends ApiStatusException
{
    public const TYPE = 'api_server_error';
}
