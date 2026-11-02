<?php

declare(strict_types=1);

namespace Unicom360\Http;

/**
 * Sends one HTTP request and returns the response.
 *
 * The default is CurlTransport; Psr18Transport adapts any PSR-18 client. A custom transport
 * (for a proxy, a test double or another HTTP library) implements this interface and:
 *
 * - returns every response, whatever its status, without following redirects;
 * - throws TransportException when no response arrived, saying whether any byte of the request
 *   may have reached the server (sent) and whether the failure was a timeout. When it cannot
 *   tell whether bytes were sent, it says sent: true. The client uses these two facts to
 *   decide whether a retry is safe.
 */
interface Transport
{
    /**
     * @throws TransportException when no complete response was received
     */
    public function send(Request $request): Response;
}
