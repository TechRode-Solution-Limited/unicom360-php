<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * The result of $client->whatsapp->status(): one entry per distinct id.
 */
final class StatusResult
{
    /**
     * @param list<MessageStatus> $results
     * @param array<mixed> $raw the whole response object
     */
    public function __construct(
        public readonly ?string $apiReference,
        public readonly ?string $message,
        public readonly array $results,
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
            array_map(MessageStatus::fromArray(...), Values::objects($payload, 'results')),
            $data,
        );
    }

    /** The status of one message, by exact id, or null. */
    public function byId(string $messageId): ?MessageStatus
    {
        foreach ($this->results as $result) {
            if ($result->messageId === $messageId) {
                return $result;
            }
        }

        return null;
    }
}
