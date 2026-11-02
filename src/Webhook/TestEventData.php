<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * The data of a webhook.test event.
 */
final class TestEventData
{
    /**
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $message,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Values::string($data, 'message'), $data);
    }
}
