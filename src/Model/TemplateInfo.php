<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * One sendable WhatsApp template, from $client->whatsapp->templates().
 */
final class TemplateInfo
{
    /**
     * @param string|null $category UTILITY, MARKETING or AUTHENTICATION (open)
     * @param string|null $from the WhatsApp number the template belongs to
     * @param list<TemplateButtonInfo> $buttons
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $language,
        public readonly ?string $category,
        public readonly ?string $from,
        public readonly ?TemplateHeaderInfo $header,
        public readonly ?TemplateBodyInfo $body,
        public readonly ?string $footer,
        public readonly array $buttons,
        public readonly ?TemplateAuthentication $authentication,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $header = Values::array($data, 'header');
        $body = Values::array($data, 'body');
        $authentication = Values::array($data, 'authentication');

        return new self(
            Values::string($data, 'name'),
            Values::string($data, 'language'),
            Values::string($data, 'category'),
            Values::stringish($data, 'from'),
            $header === null ? null : TemplateHeaderInfo::fromArray($header),
            $body === null ? null : TemplateBodyInfo::fromArray($body),
            Values::string($data, 'footer'),
            array_map(TemplateButtonInfo::fromArray(...), Values::objects($data, 'buttons')),
            $authentication === null ? null : TemplateAuthentication::fromArray($authentication),
            $data,
        );
    }
}
