<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * The result of $client->balance->all(): every bundle's units left.
 */
final class Balances
{
    /**
     * @param array<string, float> $balances bundle type (SMS, EMAIL, WHATSAPP, ...) to units left
     * @param array<mixed> $raw the whole response object
     */
    public function __construct(
        public readonly ?string $message,
        public readonly array $balances,
        public readonly ?string $clientId,
        public readonly ?string $clientName,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data the response object
     */
    public static function fromArray(array $data): self
    {
        $balances = [];
        foreach (Values::array($data, 'balances') ?? [] as $type => $value) {
            if (\is_int($value) || \is_float($value)) {
                $balances[(string) $type] = (float) $value;
            }
        }

        return new self(
            Values::string($data, 'message'),
            $balances,
            Values::stringish($data, 'client_id'),
            Values::string($data, 'client_name'),
            $data,
        );
    }

    /** The units left on one bundle type, or null when the response did not list it. */
    public function get(string $bundleType): ?float
    {
        return $this->balances[$bundleType] ?? null;
    }
}
