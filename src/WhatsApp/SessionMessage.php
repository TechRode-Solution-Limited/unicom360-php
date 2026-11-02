<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * A WhatsApp session message, ready for $client->whatsapp->sendSession(). Build it with the
 * Session factories (Session::text(), Session::image(), ...).
 *
 * Immutable: the with*() methods return a copy.
 */
final class SessionMessage implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $fields the request body in API shape
     */
    private function __construct(private readonly array $fields) {}

    /**
     * Wraps a message already in API shape, for example
     * ['to' => '260971234567', 'type' => 'text', 'text' => 'Hello'].
     *
     * @param array<string, mixed> $fields
     */
    public static function fromArray(array $fields): self
    {
        return new self($fields);
    }

    /**
     * @internal used by the Session factories
     *
     * @param array<string, mixed> $content the type's own fields
     */
    public static function build(
        string $to,
        string $type,
        array $content,
        ?string $from,
        ?string $replyTo,
        ?string $userReferenceId,
        ?string $callbackUrl,
    ): self {
        $fields = ['to' => $to, 'type' => $type] + $content;
        foreach (['from' => $from, 'reply_to' => $replyTo, 'user_reference_id' => $userReferenceId, 'callback_url' => $callbackUrl] as $key => $value) {
            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        return new self($fields);
    }

    /** The message type: text, image, video, document, audio, sticker, interactive, location, contacts or reaction. */
    public function type(): ?string
    {
        $type = $this->fields['type'] ?? null;

        return \is_string($type) ? $type : null;
    }

    public function to(): ?string
    {
        $to = $this->fields['to'] ?? null;

        return \is_string($to) ? $to : null;
    }

    /** The sending WhatsApp number, when you have more than one. */
    public function withFrom(string $from): self
    {
        return $this->with('from', $from);
    }

    /**
     * Quote an earlier message: a message_id a send returned, or the wamid. id of an inbound
     * message from a message.received webhook. Not allowed on a reaction.
     */
    public function withReplyTo(string $replyTo): self
    {
        return $this->with('reply_to', $replyTo);
    }

    /** Your reference; de-duplicated per recipient and sender, and what makes a retry safe. */
    public function withUserReferenceId(string $userReferenceId): self
    {
        return $this->with('user_reference_id', $userReferenceId);
    }

    /** An https URL for this message's status webhooks. */
    public function withCallbackUrl(string $callbackUrl): self
    {
        return $this->with('callback_url', $callbackUrl);
    }

    /**
     * The request body in API shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->fields;
    }

    private function with(string $key, string $value): self
    {
        $fields = $this->fields;
        $fields[$key] = $value;

        return new self($fields);
    }
}
