<?php

declare(strict_types=1);

namespace Unicom360\WhatsApp;

/**
 * The header parameter of a template recipient: a text value, or an image, video or document.
 */
final class TemplateHeader implements \JsonSerializable
{
    /**
     * @param string|array<string, string> $value the API value: a string, or {type, url, filename?}
     */
    private function __construct(private readonly string|array $value) {}

    /** A text header's variable value (sent as a JSON string). */
    public static function text(string $value): self
    {
        return new self($value);
    }

    public static function image(string $url): self
    {
        return new self(['type' => 'image', 'url' => $url]);
    }

    public static function video(string $url): self
    {
        return new self(['type' => 'video', 'url' => $url]);
    }

    /**
     * @param string|null $filename the name the recipient sees, for example "invoice-4821.pdf"
     */
    public static function document(string $url, ?string $filename = null): self
    {
        $value = ['type' => 'document', 'url' => $url];
        if ($filename !== null) {
            $value['filename'] = $filename;
        }

        return new self($value);
    }

    /**
     * @return string|array<string, string>
     */
    public function jsonSerialize(): string|array
    {
        return $this->value;
    }
}
