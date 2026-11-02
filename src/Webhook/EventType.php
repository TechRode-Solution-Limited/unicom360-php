<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

/**
 * Webhook event names. Open: an event this SDK does not know parses as UnknownEvent.
 */
final class EventType
{
    public const MESSAGE_STATUS = 'message.status';
    public const MESSAGE_RECEIVED = 'message.received';
    public const WEBHOOK_TEST = 'webhook.test';

    private function __construct() {}
}
