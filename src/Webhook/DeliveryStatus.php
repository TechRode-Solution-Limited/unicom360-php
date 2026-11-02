<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

/**
 * Statuses of a message.status event (lower-case). Open: compare with these and keep a default
 * branch.
 */
final class DeliveryStatus
{
    public const SENT = 'sent';
    public const DELIVERED = 'delivered';
    public const READ = 'read';
    public const FAILED = 'failed';

    private function __construct() {}
}
