<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * Where one WhatsApp message is, from $client->whatsapp->status().
 *
 * An id that is unknown (or another account's) has status NOT_FOUND and every other field null.
 */
final class MessageStatus
{
    /**
     * @param string|null $status ACCEPTED, SENT, DELIVERED, READ, FAILED, REJECTED or NOT_FOUND (open)
     * @param string|null $sentAt ISO 8601, as received
     * @param string|null $deliveredAt ISO 8601, as received
     * @param string|null $readAt ISO 8601, as received
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $status,
        public readonly ?string $userReferenceId,
        public readonly ?string $to,
        public readonly ?string $from,
        public readonly ?string $sentAt,
        public readonly ?string $deliveredAt,
        public readonly ?string $readAt,
        public readonly ?MessageError $error,
        public readonly ?string $category,
        public readonly ?bool $billable,
        public readonly ?bool $refunded,
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
            Values::stringish($data, 'user_reference_id'),
            Values::string($data, 'to'),
            Values::string($data, 'from'),
            Values::string($data, 'sent_at'),
            Values::string($data, 'delivered_at'),
            Values::string($data, 'read_at'),
            MessageError::fromField($data, 'error'),
            Values::string($data, 'category'),
            Values::bool($data, 'billable'),
            Values::bool($data, 'refunded'),
            $data,
        );
    }
}
