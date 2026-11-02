<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * webhook.test: sent by the portal's "Send test" button to check your endpoint.
 */
final class TestEvent extends Event
{
    public const TYPE = 'webhook.test';

    /**
     * @param array<mixed> $raw
     */
    public function __construct(
        string $event,
        ?string $deliveryId,
        ?string $createdAt,
        public readonly TestEventData $data,
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
            TestEventData::fromArray(Values::array($body, 'data') ?? []),
            $body,
        );
    }
}
