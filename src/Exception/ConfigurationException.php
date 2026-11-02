<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * The client was configured wrongly: no API key, an invalid base URL or an out-of-range
 * option. Raised when the client is built (or, for a per-call timeout or maxRetries, when the
 * call starts), never because of a response.
 */
class ConfigurationException extends Unicom360Exception
{
    public const TYPE = 'configuration_error';
}
