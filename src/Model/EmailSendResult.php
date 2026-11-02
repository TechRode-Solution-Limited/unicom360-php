<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Lookup;
use Unicom360\Internal\Values;

/**
 * The result of $client->email->send().
 *
 * Results are in the gateway's order, which is not the request's order past the tenth
 * recipient: match them with byAddress(), never by position.
 */
final class EmailSendResult
{
    /**
     * @param list<EmailResult> $results
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
        $items = \is_array($data['data'] ?? null) && array_is_list($data['data']) ? Values::objects($data, 'data') : [];

        return new self(
            Values::string($data, 'api_reference'),
            Values::string($data, 'message'),
            array_map(EmailResult::fromArray(...), $items),
            $data,
        );
    }

    /**
     * The results the gateway accepted (status exactly ACKNOWLEDGED), in response order.
     *
     * @return list<EmailResult>
     */
    public function accepted(): array
    {
        return array_values(array_filter($this->results, static fn(EmailResult $r): bool => $r->status === 'ACKNOWLEDGED'));
    }

    /**
     * Every other result (for example "Invalid email address"), in response order.
     *
     * @return list<EmailResult>
     */
    public function invalid(): array
    {
        return array_values(array_filter($this->results, static fn(EmailResult $r): bool => $r->status !== 'ACKNOWLEDGED'));
    }

    /**
     * The result for an address, compared after trimming whitespace and lower-casing both.
     */
    public function byAddress(string $address): ?EmailResult
    {
        return Lookup::byAddress($this->results, static fn(EmailResult $r): ?string => $r->emailAddress, $address);
    }
}
