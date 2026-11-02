<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * The body parameters of a template recipient: positional values ({{1}}, {{2}}, ...) or named
 * ones ({{name}}).
 */
final class TemplateBody implements \JsonSerializable
{
    /**
     * @param array<array-key, string> $values
     */
    private function __construct(
        private readonly array $values,
        private readonly bool $named,
    ) {}

    /**
     * Positional values, in order: TemplateBody::of(['Ada', 'ZM-4821']).
     *
     * @param list<string> $values
     */
    public static function of(array $values): self
    {
        return new self(array_values($values), false);
    }

    /**
     * Named values: TemplateBody::named(['name' => 'Ada', 'time' => '10:30']). Keys made of
     * digits ('1', '2') stay object keys.
     *
     * @param array<array-key, string> $values
     */
    public static function named(array $values): self
    {
        return new self($values, true);
    }

    /**
     * @return list<string>|\stdClass
     */
    public function jsonSerialize(): array|\stdClass
    {
        if ($this->named) {
            // Always a JSON object, even when empty or keyed by digits.
            return (object) $this->values;
        }

        return array_values($this->values);
    }
}
