<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * One recipient's result in a WhatsApp template send.
 */
final class TemplateResult
{
    /**
     * @param string|null $messageId set when the message was queued, or (reason
     *                               DUPLICATE_MSG_REF) the original message's id; else null
     * @param string|null $to the recipient as the request sent it (a string even when the
     *                        request sent a number)
     * @param string|null $status ACKNOWLEDGED or REJECTED (open)
     * @param string|null $reason why it was rejected (open; null when accepted), for example
     *                            INVALID_NUMBER or DUPLICATE_MSG_REF
     * @param array<mixed> $raw the JSON object this result was parsed from
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $to,
        public readonly ?string $userReferenceId,
        public readonly ?string $status,
        public readonly ?string $reason,
        public readonly ?string $category,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::stringish($data, 'message_id'),
            Values::stringish($data, 'to'),
            Values::stringish($data, 'user_reference_id'),
            Values::string($data, 'status'),
            Values::string($data, 'reason'),
            Values::string($data, 'category'),
            $data,
        );
    }

    /** Whether the message was queued (status ACKNOWLEDGED). */
    public function isAccepted(): bool
    {
        return $this->status === 'ACKNOWLEDGED';
    }
}
