<?php

declare(strict_types=1);

namespace Unicom360\Resource;

use Unicom360\Exception\Unicom360Exception;
use Unicom360\Internal\Requester;
use Unicom360\Model\Balance;
use Unicom360\Model\Balances;

/**
 * Bundle balances: $client->balance.
 */
final class BalanceResource
{
    /**
     * @internal
     */
    public function __construct(private readonly Requester $requester) {}

    /**
     * The units left on one bundle: SMS when $bundleType is null, else the type given
     * (Unicom360\BundleType::WHATSAPP, ...).
     *
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function get(?string $bundleType = null, ?float $timeout = null, ?int $maxRetries = null): Balance
    {
        $body = $bundleType === null ? [] : ['bundle_type' => $bundleType];

        return Balance::fromArray($this->requester->request('balance.get', $body, $timeout, $maxRetries));
    }

    /**
     * The units left on every bundle.
     *
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function all(?float $timeout = null, ?int $maxRetries = null): Balances
    {
        return Balances::fromArray($this->requester->request('balance.all', [], $timeout, $maxRetries));
    }
}
