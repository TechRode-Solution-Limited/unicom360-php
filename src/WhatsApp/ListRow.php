<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * A row of a list message section (Session::list()).
 */
final class ListRow implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $description = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        $out = ['id' => $this->id, 'title' => $this->title];
        if ($this->description !== null) {
            $out['description'] = $this->description;
        }

        return $out;
    }
}
