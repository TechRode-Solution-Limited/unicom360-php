<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * message.status: a WhatsApp message you sent was sent, delivered, read or failed.
 */
final class MessageStatusEvent extends Event
{
    public const TYPE = 'message.status';

    /**
     * @param array<mixed> $raw
     */
    public function __construct(
        string $event,
        ?string $deliveryId,
        ?string $createdAt,
        public readonly MessageStatusData $data,
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
            MessageStatusData::fromArray(Values::array($body, 'data') ?? []),
            $body,
        );
    }
}
