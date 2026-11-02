<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * An event this version of the SDK does not know. It is verified like any other; its data is
 * kept as decoded JSON. Answer 2xx so the gateway does not retry it.
 */
final class UnknownEvent extends Event
{
    /**
     * @param array<mixed>|null $data the body's data, as decoded JSON
     * @param array<mixed> $raw
     */
    public function __construct(
        string $event,
        ?string $deliveryId,
        ?string $createdAt,
        public readonly ?array $data,
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
            Values::string($body, 'event') ?? '',
            $deliveryId,
            Values::string($body, 'created_at'),
            Values::array($body, 'data'),
            $body,
        );
    }
}
