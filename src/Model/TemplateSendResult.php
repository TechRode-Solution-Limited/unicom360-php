<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Lookup;
use Unicom360\Internal\Values;

/**
 * The result of $client->whatsapp->sendTemplate().
 *
 * A send with some rejected recipients still succeeds: check rejected(). When every recipient
 * is rejected the call throws NoValidRecipientsException instead.
 */
final class TemplateSendResult
{
    /**
     * @param int|null $acceptedCount how many recipients were queued (the API's data.accepted)
     * @param int|null $rejectedCount how many were rejected (the API's data.rejected)
     * @param list<TemplateResult> $results one per recipient
     * @param array<mixed> $raw the whole response object
     */
    public function __construct(
        public readonly ?string $apiReference,
        public readonly ?string $message,
        public readonly ?int $acceptedCount,
        public readonly ?int $rejectedCount,
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
            Values::int($payload, 'accepted'),
            Values::int($payload, 'rejected'),
            array_map(TemplateResult::fromArray(...), Values::objects($payload, 'results')),
            $data,
        );
    }

    /**
     * The results that were queued (status exactly ACKNOWLEDGED).
     *
     * @return list<TemplateResult>
     */
    public function accepted(): array
    {
        return array_values(array_filter($this->results, static fn(TemplateResult $r): bool => $r->status === 'ACKNOWLEDGED'));
    }

    /**
     * The results that were rejected (status exactly REJECTED); see each one's reason.
     *
     * @return list<TemplateResult>
     */
    public function rejected(): array
    {
        return array_values(array_filter($this->results, static fn(TemplateResult $r): bool => $r->status === 'REJECTED'));
    }

    /**
     * The result for a number, compared by digits only.
     */
    public function byNumber(string $number): ?TemplateResult
    {
        return Lookup::byNumber($this->results, static fn(TemplateResult $r): ?string => $r->to, $number);
    }
}
