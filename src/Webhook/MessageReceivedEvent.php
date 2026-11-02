<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * message.received: a customer sent your WhatsApp number a message. It opens (or extends) the
 * 24-hour window in which you can send session messages.
 */
final class MessageReceivedEvent extends Event
{
    public const TYPE = 'message.received';

    /**
     * @param array<mixed> $raw
     */
    public function __construct(
        string $event,
        ?string $deliveryId,
        ?string $createdAt,
        public readonly InboundMessage $data,
        array $raw,
    ) {
        parent::__construct($event, $deliveryId, $createdAt, $raw);
    }

    /**
     * @param array<mixed> $body
     */
    public static function fromArray(array $body, ?string $deliveryId): self
    {
        return new self(
            Values::string($body, 'event') ?? self::TYPE,
            $deliveryId,
            Values::string($body, 'created_at'),
            InboundMessage::fromArray(Values::array($body, 'data') ?? []),
            $body,
        );
    }
}
