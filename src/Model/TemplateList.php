<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * The result of $client->whatsapp->templates(): the approved templates you can send.
 */
final class TemplateList
{
    /**
     * @param list<TemplateInfo> $templates
     * @param array<mixed> $raw the whole response object
     */
    public function __construct(
        public readonly ?string $apiReference,
        public readonly ?string $message,
        public readonly array $templates,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data the response object
     */
    public static function fromArray(array $data): self
    {
        $payload = Values::array($data, 'data') ?? [];

        return new self(
            Values::string($data, 'api_reference'),
            Values::string($data, 'message'),
            array_map(TemplateInfo::fromArray(...), Values::objects($payload, 'templates')),
            $data,
        );
    }

    /** The first template with this exact name (and language, when given), or null. */
    public function byName(string $name, ?string $language = null): ?TemplateInfo
    {
        foreach ($this->templates as $template) {
            if ($template->name === $name && ($language === null || $template->language === $language)) {
                return $template;
            }
        }

        return null;
    }
}
