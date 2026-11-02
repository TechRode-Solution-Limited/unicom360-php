<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * The data of a message.received event.
 *
 * - A caption arrives in $text, not in $media.
 * - $payload is set only for location, contacts ({"contacts": [...]}), reaction, sticker and
 *   unsupported messages, and null otherwise.
 * - An interactive or button reply has the chosen title in $text, $payload null, and $replyTo
 *   set to the wamid. of your interactive message; the button or row id is not passed on.
 */
final class InboundMessage
{
    /**
     * @param string|null $messageId Meta's id ("wamid. ..."): pass it as replyTo, or to
     *                               Session::reaction()
     * @param string|null $channel "whatsapp"
     * @param string|null $from the customer's number
     * @param string|null $to your WhatsApp number
     * @param string|null $type text, image, document, audio, video, sticker, interactive, button,
     *                          location, contacts, reaction, ... (open)
     * @param array<mixed>|null $payload Meta's object for location, contacts, reaction, sticker
     *                                   and unsupported messages
     * @param string|null $replyTo the wamid. of the message this one replies to, or null
     * @param string|null $windowExpiresAt when the 24-hour session window closes, ISO 8601
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $channel,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?string $type,
        public readonly ?string $text,
        public readonly ?InboundMedia $media,
        public readonly ?array $payload,
        public readonly ?string $replyTo,
        public readonly ?string $timestamp,
        public readonly ?string $windowExpiresAt,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $media = Values::array($data, 'media');

        return new self(
            Values::stringish($data, 'message_id'),
            Values::string($data, 'channel'),
            Values::string($data, 'from'),
            Values::string($data, 'to'),
            Values::string($data, 'type'),
            Values::string($data, 'text'),
            $media === null ? null : InboundMedia::fromArray($media),
            Values::array($data, 'payload'),
            Values::string($data, 'reply_to'),
            Values::string($data, 'timestamp'),
            Values::string($data, 'window_expires_at'),
            $data,
        );
    }
}
