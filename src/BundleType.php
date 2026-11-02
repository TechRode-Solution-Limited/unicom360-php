<?php

declare(strict_types=1);

namespace Unicom360;

/**
 * Bundle types for $client->balance->get(). The list is open: any string the gateway accepts
 * can be passed.
 */
final class BundleType
{
    public const SMS = 'SMS';
    public const SMS_INTERNATIONAL = 'SMS_INTERNATIONAL';
    public const EMAIL = 'EMAIL';
    public const WHATSAPP = 'WHATSAPP';

    private function __construct() {}
}
