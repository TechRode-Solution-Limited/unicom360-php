<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * One recipient's result in an SMS send.
 */
final class SmsResult
{
    /**
     * @param string|null $messageId the message's id, for matching delivery reports
     * @param string|null $status ACKNOWLEDGED, or "Invalid mobile number" (open)
     * @param string|null $mobileNumber the number as the gateway normalised it
     * @param array<mixed> $raw the JSON object this result was parsed from
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

    /** Whether the gateway accepted this recipient (status ACKNOWLEDGED). */
    public function isAccepted(): bool
    {
        return $this->status === 'ACKNOWLEDGED';
    }
}
