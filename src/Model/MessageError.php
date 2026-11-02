<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * Why a WhatsApp message failed: Meta's error code (null when there is none) and message.
 */
final class MessageError
{
    /**
     * @param array<mixed> $raw
     */
    public function __construct(
        public readonly ?int $code,
        public readonly ?string $message,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Values::int($data, 'code'), Values::string($data, 'message'), $data);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromField(array $data, string $key): ?self
    {
        $value = Values::array($data, $key);

        return $value === null ? null : self::fromArray($value);
    }
}
