<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * A section of a list message (Session::list()): an optional title and its rows.
 */
final class ListSection implements \JsonSerializable
{
    /**
     * @param list<ListRow|array<string, string>> $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?string $title = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $out = [];
        if ($this->title !== null) {
            $out['title'] = $this->title;
        }
        $out['rows'] = array_values($this->rows);

        return $out;
    }
}
