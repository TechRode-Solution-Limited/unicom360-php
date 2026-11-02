<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

/**
 * A verified webhook event, from Unicom360\Webhook::constructEvent(). Check its class:
 * MessageStatusEvent, MessageReceivedEvent, TestEvent or UnknownEvent.
 */
abstract class Event
{
    /**
     * @param string $event the body's event, for example "message.status"
     * @param string|null $deliveryId the X-Unicom360-Delivery header: the same for every retry of
     *                                one delivery, so use it to drop repeats
     * @param string|null $createdAt when the event happened, ISO 8601, as received
     * @param array<mixed> $raw the whole body
     */
    public function __construct(
        public readonly string $event,
        public readonly ?string $deliveryId,
        public readonly ?string $createdAt,
        public readonly array $raw,
    ) {}
}
