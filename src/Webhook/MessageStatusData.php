<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;
use Unicom360\Model\MessageError;

/**
 * The data of a message.status event.
 */
final class MessageStatusData
{
    /**
     * @param string|null $messageId the message_id the send returned
     * @param string|null $channel "whatsapp"
     * @param string|null $to the customer's number
     * @param string|null $from your WhatsApp number
     * @param string|null $status sent, delivered, read or failed (open; see DeliveryStatus)
     * @param string|null $timestamp when the status changed, ISO 8601, as received
     * @param MessageError|null $error why it failed, or null
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $userReferenceId,
        public readonly ?string $channel,
        public readonly ?string $to,
        public readonly ?string $from,
        public readonly ?string $status,
        public readonly ?string $timestamp,
        public readonly ?MessageError $error,
        public readonly ?string $category,
        public readonly ?bool $billable,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::stringish($data, 'message_id'),
            Values::stringish($data, 'user_reference_id'),
            Values::string($data, 'channel'),
            Values::string($data, 'to'),
            Values::string($data, 'from'),
            Values::string($data, 'status'),
            Values::string($data, 'timestamp'),
            MessageError::fromField($data, 'error'),
            Values::string($data, 'category'),
            Values::bool($data, 'billable'),
            $data,
        );
    }
}
