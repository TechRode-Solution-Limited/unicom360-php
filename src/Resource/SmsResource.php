<?php

declare(strict_types=1);

namespace Unicom360\Resource;

use Unicom360\Exception\Unicom360Exception;
use Unicom360\Internal\Params;
use Unicom360\Internal\Requester;
use Unicom360\Model\SmsSendResult;

/**
 * SMS: $client->sms.
 */
final class SmsResource
{
    /**
     * @internal
     */
    public function __construct(private readonly Requester $requester) {}

    /**
     * Sends one GSM-7 message to one or more numbers.
     *
     * Pass named arguments, or one array keyed by the API's field names:
     *
     *     $client->sms->send(from: 'Unicom360', to: '260971234567', message: 'Hello');
     *     $client->sms->send(['from' => 'Unicom360', 'to' => ['260971234567'], 'message' => 'Hello']);
     *
     * Only GSM-7 text is accepted, up to 1,200 characters; see Unicom360\Gsm to check text
     * before sending. With a userReferenceId the send is de-duplicated per recipient and sender
     * and is safe to retry after a timeout or a 5xx.
     *
     * @param string|array<string, mixed> $from the sender ID, or the whole request keyed by API names
     * @param string|list<string>|null $to one number or a list, in international form; a
     *                                     string is sent as a string, a list as a list
     * @param string|null $callbackUrl where the gateway posts delivery reports (unsigned)
     * @param string|null $source a free-form label for your own reporting
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function send(
        string|array $from,
        string|array|null $to = null,
        ?string $message = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
        ?string $source = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
    ): SmsSendResult {
        $body = Params::merge(\is_array($from) ? $from : ['from' => $from], [
            'to' => $to,
            'message' => $message,
            'user_reference_id' => $userReferenceId,
            'callback_url' => $callbackUrl,
            'source' => $source,
        ]);

        return SmsSendResult::fromArray($this->requester->request('sms.send', $body, $timeout, $maxRetries));
    }
}
