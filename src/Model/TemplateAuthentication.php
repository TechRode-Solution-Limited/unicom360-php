<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * The OTP settings of an AUTHENTICATION template.
 */
final class TemplateAuthentication
{
    /**
     * @param string|null $otpType COPY_CODE, ONE_TAP or ZERO_TAP (open)
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?string $otpType,
        public readonly ?int $codeExpirationMinutes,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Values::string($data, 'otp_type'), Values::int($data, 'code_expiration_minutes'), $data);
    }
}
