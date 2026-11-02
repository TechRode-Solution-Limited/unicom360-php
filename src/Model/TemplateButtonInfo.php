<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * One button of a template: its index, type (url, quick_reply, otp, phone_number; open) and,
 * depending on the type, its text, URL pattern or OTP type.
 */
final class TemplateButtonInfo
{
    /**
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?int $index,
        public readonly ?string $type,
        public readonly ?string $text,
        public readonly ?string $url,
        public readonly ?string $otpType,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::int($data, 'index'),
            Values::string($data, 'type'),
            Values::string($data, 'text'),
            Values::string($data, 'url'),
            Values::string($data, 'otp_type'),
            $data,
        );
    }
}
