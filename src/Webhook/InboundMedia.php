<?php

declare(strict_types=1);

namespace Unicom360\Webhook;

use Unicom360\Internal\Values;

/**
 * The media of an inbound image, video, audio, document or sticker. A caption arrives in the
 * message's text, not here.
 */
final class InboundMedia
{
    /**
     * @param string|null $id Meta's media id
     * @param string|null $filename set for documents
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $mimeType,
        public readonly ?string $filename,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::stringish($data, 'id'),
            Values::string($data, 'mime_type'),
            Values::string($data, 'filename'),
            $data,
        );
    }
}
