<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * Holds a secret (the API key) outside any property, so var_dump, print_r, var_export,
 * json_encode and serialize cannot reveal it.
 *
 * @internal
 */
final class Secret implements \JsonSerializable
{
    /** @var \WeakMap<Secret, string>|null */
    private static ?\WeakMap $values = null;

    public function __construct(#[\SensitiveParameter] string $value)
    {
        self::store()[$this] = $value;
    }

    public function reveal(): string
    {
        return self::store()[$this] ?? '';
    }

    private function __clone() {}

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    public function __toString(): string
    {
        return '[REDACTED]';
    }

    public function jsonSerialize(): string
    {
        return '[REDACTED]';
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new \LogicException('A Unicom360 secret cannot be serialized.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('A Unicom360 secret cannot be unserialized.');
    }

    /**
     * @return \WeakMap<Secret, string>
     */
    private static function store(): \WeakMap
    {
        return self::$values ??= new \WeakMap();
    }
}
