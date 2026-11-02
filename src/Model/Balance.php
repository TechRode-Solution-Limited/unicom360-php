<?php

declare(strict_types=1);

namespace Unicom360\Model;

use Unicom360\Internal\Values;

/**
 * The result of $client->balance->get(): the units left on one bundle.
 */
final class Balance
{
    /**
     * @param float|null $balance units left (a whole number today; typed as a number so a
     *                            fractional balance needs no change)
     * @param string|null $bundleType SMS, SMS_INTERNATIONAL, EMAIL or WHATSAPP (open)
     * @param array<mixed> $raw the whole response object
     */
    public function __construct(
        public readonly ?string $message,
        public readonly ?float $balance,
        public readonly ?string $bundleType,
        public readonly ?string $clientId,
        public readonly ?string $clientName,
        public readonly array $raw,
    ) {}

    /**
     * @param array<mixed> $data the response object
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'message'),
            Values::float($data, 'balance'),
            Values::string($data, 'bundle_type'),
            Values::stringish($data, 'client_id'),
            Values::string($data, 'client_name'),
            $data,
        );
    }
}
