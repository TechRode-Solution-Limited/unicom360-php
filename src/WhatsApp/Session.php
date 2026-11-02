<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * Factories for WhatsApp session messages (free-form messages, allowed within 24 hours of the
 * customer's last message).
 *
 *     $client->whatsapp->sendSession(Session::text('260971234567', 'Your order is ready.'));
 *
 * Every factory takes the optional named arguments from (your sending number), replyTo (a
 * message to quote: a message_id from a send, or the wamid. id of an inbound message),
 * userReferenceId and callbackUrl. reaction() takes no replyTo.
 *
 * The SDK checks nothing: lengths, URLs and emoji are validated by the gateway, which answers
 * InvalidRequestException with its explanation.
 */
final class Session
{
    private function __construct() {}

    public static function text(
        string $to,
        string $text,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'text', ['text' => $text], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    public static function image(
        string $to,
        string $url,
        ?string $caption = null,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'image', ['media' => self::media($url, $caption, null)], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    public static function video(
        string $to,
        string $url,
        ?string $caption = null,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'video', ['media' => self::media($url, $caption, null)], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    /**
     * @param string|null $filename the name the recipient sees, for example "invoice-4821.pdf"
     */
    public static function document(
        string $to,
        string $url,
        ?string $caption = null,
        ?string $filename = null,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'document', ['media' => self::media($url, $caption, $filename)], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    public static function audio(
        string $to,
        string $url,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'audio', ['media' => self::media($url, null, null)], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    public static function sticker(
        string $to,
        string $url,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'sticker', ['media' => self::media($url, null, null)], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    /**
     * Up to three reply buttons.
     *
     * @param list<ReplyButton|array{id: string, title: string}> $buttons
     * @param string|null $header a text header
     */
    public static function buttons(
        string $to,
        string $body,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        $interactive = ['type' => 'button'];
        if ($header !== null) {
            $interactive['header'] = $header;
        }
        $interactive['body'] = $body;
        if ($footer !== null) {
            $interactive['footer'] = $footer;
        }
        $interactive['buttons'] = array_values($buttons);

        return SessionMessage::build($to, 'interactive', ['interactive' => $interactive], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    /**
     * A list message: a button that opens sections of rows.
     *
     * @param string $button the label of the button that opens the list
     * @param list<ListSection|array<string, mixed>> $sections
     * @param string|null $header a text header
     */
    public static function list(
        string $to,
        string $body,
        string $button,
        array $sections,
        ?string $header = null,
        ?string $footer = null,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        $interactive = ['type' => 'list'];
        if ($header !== null) {
            $interactive['header'] = $header;
        }
        $interactive['body'] = $body;
        if ($footer !== null) {
            $interactive['footer'] = $footer;
        }
        $interactive['button'] = $button;
        $interactive['sections'] = array_values($sections);

        return SessionMessage::build($to, 'interactive', ['interactive' => $interactive], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    public static function location(
        string $to,
        int|float $latitude,
        int|float $longitude,
        ?string $name = null,
        ?string $address = null,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        $location = ['latitude' => $latitude, 'longitude' => $longitude];
        if ($name !== null) {
            $location['name'] = $name;
        }
        if ($address !== null) {
            $location['address'] = $address;
        }

        return SessionMessage::build($to, 'location', ['location' => $location], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    /**
     * @param list<ContactCard|array<string, mixed>> $contacts Meta's contact objects
     */
    public static function contacts(
        string $to,
        array $contacts,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'contacts', ['contacts' => array_values($contacts)], $from, $replyTo, $userReferenceId, $callbackUrl);
    }

    /**
     * React to a message, or remove your reaction with an empty $emoji.
     *
     * @param string $messageId a message_id from a send, or the wamid. id of an inbound message
     */
    public static function reaction(
        string $to,
        string $messageId,
        string $emoji,
        ?string $from = null,
        ?string $userReferenceId = null,
        ?string $callbackUrl = null,
    ): SessionMessage {
        return SessionMessage::build($to, 'reaction', ['reaction' => ['message_id' => $messageId, 'emoji' => $emoji]], $from, null, $userReferenceId, $callbackUrl);
    }

    /**
     * @return array<string, string>
     */
    private static function media(string $url, ?string $caption, ?string $filename): array
    {
        $media = ['url' => $url];
        if ($caption !== null) {
            $media['caption'] = $caption;
        }
        if ($filename !== null) {
            $media['filename'] = $filename;
        }

        return $media;
    }
}
