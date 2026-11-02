<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * The result of $client->whatsapp->sendSession().
 */
final class SessionSendResult
{
    /**
     * @param string|null $messageId the message's id: pass it to status(), or as replyTo
     * @param string|null $status ACKNOWLEDGED (open)
     * @param string|null $category SERVICE (open)
     * @param array<mixed> $raw the whole response object
     */
    public function __construct(
        public readonly ?string $apiReference,
        public readonly ?string $message,
        public readonly ?string $messageId,
        public readonly ?string $to,
        public readonly ?string $userReferenceId,
        public readonly ?string $status,
        public readonly ?string $category,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data the response object
     */
    public static function fromArray(array $data): self
    {
        $payload = Values::array($data, 'data') ?? [];

        return new self(
            Values::string($data, 'api_reference'),
            Values::string($data, 'message'),
            Values::stringish($payload, 'message_id'),
            Values::stringish($payload, 'to'),
            Values::stringish($payload, 'user_reference_id'),
            Values::string($payload, 'status'),
            Values::string($payload, 'category'),
            $data,
        );
    }
}
