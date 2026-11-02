<?php

declare(strict_types=1);

namespace Unicom360\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends requests through any PSR-18 HTTP client (Guzzle, Symfony HttpClient, ...).
 *
 * Needs psr/http-client and psr/http-factory (suggested, not required).
 *
 * Configure the PSR-18 client yourself: its timeouts apply (PSR-18 has no per-request timeout),
 * and it must not follow redirects, or the X-API-Key header could be replayed to another host
 * (Guzzle: 'allow_redirects' => false).
 *
 * PSR-18 cannot say whether a failed request reached the server, so every failure is treated
 * as possibly sent: it is retried only when the operation is retry-safe.
 */
final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function send(Request $request): Response
    {
        $psrRequest = $this->requestFactory->createRequest($request->method, $request->url);
        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }
        if ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
            $body = (string) $psrResponse->getBody();
        } catch (ClientExceptionInterface|\RuntimeException $e) {
            throw new TransportException(
                'The PSR-18 client failed: ' . $e->getMessage(),
                true,
                self::isTimeout($e),
                $e,
            );
        }

        $headers = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[(string) $name] = implode(', ', $values);
        }

        return new Response($psrResponse->getStatusCode(), $headers, $body);
    }

    private static function isTimeout(\Throwable $error): bool
    {
        for ($e = $error; $e !== null; $e = $e->getPrevious()) {
            if (preg_match('/timed?[ -]?out|timeout/i', $e->getMessage()) === 1) {
                return true;
            }
        }

        return false;
    }
}
