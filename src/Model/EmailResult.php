<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * One recipient's result in an email send.
 */
final class EmailResult
{
    /**
     * @param string|null $messageId the message's id (always a string, though the delivery
     *                               report sends it as a number)
     * @param string|null $status ACKNOWLEDGED, or "Invalid email address" (open)
     * @param array<mixed> $raw the JSON object this result was parsed from
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $status,
        public readonly ?string $emailAddress,
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
            $data,
        );
    }

    /** Whether the gateway accepted this recipient (status ACKNOWLEDGED). */
    public function isAccepted(): bool
    {
        return $this->status === 'ACKNOWLEDGED';
    }
}
