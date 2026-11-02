<?php

declare(strict_types=1);

namespace Unicom360\Resource;

use Unicom360\Exception\Unicom360Exception;
use Unicom360\Internal\Params;
use Unicom360\Internal\Requester;
use Unicom360\Model\SessionSendResult;
use Unicom360\Model\StatusResult;
use Unicom360\Model\TemplateList;
use Unicom360\Model\TemplateSendResult;
use Unicom360\WhatsApp\SessionMessage;
use Unicom360\WhatsApp\TemplateRecipient;

/**
 * WhatsApp: $client->whatsapp.
 */
final class WhatsAppResource
{
    /**
     * @internal
     */
    public function __construct(private readonly Requester $requester) {}

    /**
     * Sends an approved template to up to 1,000 recipients, each with its own parameters.
     *
     *     $client->whatsapp->sendTemplate(
     *         template: 'order_ready',
     *         language: 'en_US',
     *         recipients: [
     *             new TemplateRecipient('260971234567', userReferenceId: 'inv-42', body: TemplateBody::of(['Ada', 'ZM-4821'])),
     *         ],
     *     );
     *
     * Rejected recipients do not make the call fail: see the result's rejected(). When every
     * recipient is rejected, NoValidRecipientsException is thrown. The send is retried after a
     * timeout or a 5xx only when every recipient has a userReferenceId.
     *
     * @param string|array<string, mixed> $template the template's name, or the whole request
     *                                              keyed by API names
     * @param list<TemplateRecipient|array<string, mixed>>|null $recipients
     * @param string|null $from your WhatsApp number, when you have more than one
     * @param string|null $language the template language, for example en_US
     * @param string|null $callbackUrl an https URL for these messages' status webhooks
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function sendTemplate(
        string|array $template,
        ?array $recipients = null,
        ?string $from = null,
        ?string $language = null,
        ?string $callbackUrl = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
    ): TemplateSendResult {
        $body = Params::merge(\is_array($template) ? $template : ['template' => $template], [
            'from' => $from,
            'language' => $language,
            'callback_url' => $callbackUrl,
            'recipients' => $recipients === null ? null : array_values($recipients),
        ]);

        return TemplateSendResult::fromArray($this->requester->request('whatsapp.send_template', $body, $timeout, $maxRetries));
    }

    /**
     * Sends a free-form session message, allowed within 24 hours of the customer's last
     * message. Build it with Unicom360\WhatsApp\Session, or pass an array in API shape.
     *
     *     $client->whatsapp->sendSession(Session::text('260971234567', 'Your order is ready.'));
     *
     * Outside the window, SessionWindowClosedException is thrown: send a template instead.
     *
     * @param SessionMessage|array<string, mixed> $message
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function sendSession(SessionMessage|array $message, ?float $timeout = null, ?int $maxRetries = null): SessionSendResult
    {
        $body = Params::merge($message instanceof SessionMessage ? $message->toArray() : $message, []);

        return SessionSendResult::fromArray($this->requester->request('whatsapp.send_session', $body, $timeout, $maxRetries));
    }

    /**
     * Where up to 100 of your WhatsApp messages are, by the message_id a send returned. The ids
     * are sent as given; the gateway answers each distinct id once.
     *
     * @param array<string> $messageIds sent as a JSON list, in this order
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function status(array $messageIds, ?float $timeout = null, ?int $maxRetries = null): StatusResult
    {
        return StatusResult::fromArray($this->requester->request(
            'whatsapp.status',
            ['message_ids' => array_values($messageIds)],
            $timeout,
            $maxRetries,
        ));
    }

    /**
     * The approved templates you can send, with the parameters each one takes.
     *
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function templates(?float $timeout = null, ?int $maxRetries = null): TemplateList
    {
        return TemplateList::fromArray($this->requester->request('whatsapp.templates', null, $timeout, $maxRetries));
    }
}
