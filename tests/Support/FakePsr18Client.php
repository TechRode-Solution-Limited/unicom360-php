<?php

declare(strict_types=1);

namespace Unicom360\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that records requests and answers through a closure.
 */
final class FakePsr18Client implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param \Closure(RequestInterface): ResponseInterface $handler
     */
    public function __construct(private readonly \Closure $handler) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return ($this->handler)($request);
    }
}
