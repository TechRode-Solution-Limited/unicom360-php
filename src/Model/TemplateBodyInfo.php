<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * A template's body text and the variables it takes ("1", "2" for positional bodies, names for
 * named ones).
 */
final class TemplateBodyInfo
{
    /**
     * @param list<string> $variables
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $variables,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Values::string($data, 'text'), Values::strings($data, 'variables'), $data);
    }
}
