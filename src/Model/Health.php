<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * The result of $client->health(): the gateway is up.
 */
final class Health
{
    /**
     * @param string|null $status "ok"
     * @param string|null $timestamp the gateway's clock, ISO 8601 with microseconds, as received
     * @param array<mixed> $raw the whole response object
     */
    public function __construct(
        public readonly ?string $status,
        public readonly ?string $timestamp,
        public readonly ?string $version,
        public readonly ?string $environment,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data the response object
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'status'),
            Values::string($data, 'timestamp'),
            Values::string($data, 'version'),
            Values::string($data, 'environment'),
            $data,
        );
    }
}
