<?php

declare(strict_types=1);

namespace Unicom360\Resource;

use Unicom360\Exception\Unicom360Exception;
use Unicom360\Internal\Params;
use Unicom360\Internal\Requester;
use Unicom360\Model\EmailSendResult;

/**
 * Email: $client->email.
 */
final class EmailResource
{
    /**
     * @internal
     */
    public function __construct(private readonly Requester $requester) {}

    /**
     * Sends one email to one or more addresses.
     *
     *     $client->email->send(
     *         from: 'noreply@yourdomain.com',
     *         to: 'jane@example.com',
     *         subject: 'Your order is confirmed',
     *         emailBody: '<p>Thanks for your order.</p>',
     *     );
     *
     * Email is never retried after a timeout or a 5xx, even with a userReferenceId: the
     * gateway's duplicate check for email is not atomic, so a retry could send twice. It is
     * retried only on a 429 and when the connection failed before anything was sent.
     *
     * @param string|array<string, mixed> $from a verified sender address, or the whole request
     *                                          keyed by API names
     * @param string|list<string>|null $to one address or a list
     * @param string|null $emailBody HTML or plain text
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function send(
        string|array $from,
        string|array|null $to = null,
        ?string $subject = null,
        ?string $emailBody = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
        ?string $source = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
    ): EmailSendResult {
        $body = Params::merge(\is_array($from) ? $from : ['from' => $from], [
            'to' => $to,
            'subject' => $subject,
            'email_body' => $emailBody,
            'reply_to' => $replyTo,
            'user_reference_id' => $userReferenceId,
            'callback_url' => $callbackUrl,
            'source' => $source,
        ]);

        return EmailSendResult::fromArray($this->requester->request('email.send', $body, $timeout, $maxRetries));
    }
}
