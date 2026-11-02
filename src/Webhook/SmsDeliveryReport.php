<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * An SMS delivery report, from Unicom360\Webhook::parseSmsCallback().
 *
 * Unsigned and never retried: anyone who knows your callback URL can post one, and a lost one
 * is not resent. Treat it as a hint.
 */
final class SmsDeliveryReport
{
    /**
     * @param string|null $status SENT, DELIVERED, FAILED or UNKNOWN_ERROR (open)
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $status,
        public readonly ?string $mobileNumber,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::stringish($data, 'message_id'),
            Values::string($data, 'status'),
            Values::stringish($data, 'mobile_number'),
            $data,
        );
    }
}
