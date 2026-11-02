<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * A button parameter of a template recipient.
 */
final class TemplateButton implements \JsonSerializable
{
    private function __construct(
        private readonly int $index,
        private readonly string $type,
        private readonly string $value,
    ) {}

    /**
     * The dynamic suffix of the URL button at $index (0-based).
     */
    public static function url(int $index, string $value): self
    {
        return new self($index, 'url', $value);
    }

    /**
     * The payload of the quick-reply button at $index (0-based).
     */
    public static function quickReply(int $index, string $value): self
    {
        return new self($index, 'quick_reply', $value);
    }

    /**
     * @return array{index: int, type: string, value: string}
     */
    public function jsonSerialize(): array
    {
        return ['index' => $this->index, 'type' => $this->type, 'value' => $this->value];
    }
}
