<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * An email delivery report, from Unicom360\Webhook::parseEmailCallback().
 *
 * Unsigned and never retried: anyone who knows your callback URL can post one, and a lost one
 * is not resent. Treat it as a hint.
 */
final class EmailDeliveryReport
{
    /**
     * @param string|null $messageId the message_id the send returned (the report sends a
     *                               number; it is turned into the same string)
     * @param string|null $status SENT, DELIVERED or FAILED (open)
     * @param string|null $message the mail server's answer, for example "550 5.1.1 mailbox unavailable"
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $status,
        public readonly ?string $emailAddress,
        public readonly ?string $message,
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
            Values::string($data, 'email_address'),
            Values::string($data, 'message'),
            $data,
        );
    }
}
