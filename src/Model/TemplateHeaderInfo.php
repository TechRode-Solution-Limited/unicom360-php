<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * A template's header: its type (TEXT, IMAGE, VIDEO, DOCUMENT; open) and the variables it takes.
 */
final class TemplateHeaderInfo
{
    /**
     * @param list<string> $variables
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $type,
        public readonly array $variables,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Values::string($data, 'type'), Values::strings($data, 'variables'), $data);
    }
}
