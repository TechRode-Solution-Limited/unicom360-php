<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * A WhatsApp session message was refused because the 24-hour customer service window is closed
 * (code SESSION_WINDOW_CLOSED). Send an approved template instead.
 */
class SessionWindowClosedException extends ApiException
{
    public const TYPE = 'api_error';
}
