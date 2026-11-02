<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * The bundle for this channel has too few units left (code INSUFFICIENT_BUNDLE). Top up in the
 * portal; retrying will not help.
 */
class InsufficientBundleException extends ApiException
{
    public const TYPE = 'api_error';
}
