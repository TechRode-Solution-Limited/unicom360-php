<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Lookup;
use Unicom360\Internal\Values;

/**
 * The result of $client->sms->send().
 *
 * Results are in the gateway's order, which is not the request's order past the tenth
 * recipient: match them with byNumber(), never by position. A result with status
 * "Invalid mobile number" is not an error; the rest of the send went through.
 */
final class SmsSendResult
{
    /**
     * @param list<SmsResult> $results
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
            array_map(SmsResult::fromArray(...), $items),
            $data,
        );
    }

    /**
     * The results the gateway accepted (status exactly ACKNOWLEDGED), in response order.
     *
     * @return list<SmsResult>
     */
    public function accepted(): array
    {
        return array_values(array_filter($this->results, static fn(SmsResult $r): bool => $r->status === 'ACKNOWLEDGED'));
    }

    /**
     * Every other result (for example "Invalid mobile number"), in response order.
     *
     * @return list<SmsResult>
     */
    public function invalid(): array
    {
        return array_values(array_filter($this->results, static fn(SmsResult $r): bool => $r->status !== 'ACKNOWLEDGED'));
    }

    /**
     * The result for a number, compared by digits only ("+260 97 123 4567" finds
     * "260971234567"). Pass the international form: local formats are not expanded.
     */
    public function byNumber(string $number): ?SmsResult
    {
        return Lookup::byNumber($this->results, static fn(SmsResult $r): ?string => $r->mobileNumber, $number);
    }
}
