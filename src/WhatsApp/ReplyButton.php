<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * A reply button of an interactive session message (Session::buttons()).
 *
 * The customer's reply arrives as a message.received webhook with the button's title in
 * data.text; the id is not passed on.
 */
final class ReplyButton implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
    ) {}

    /**
     * @return array{id: string, title: string}
     */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'title' => $this->title];
    }
}
