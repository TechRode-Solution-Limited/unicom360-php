<?php

declare(strict_types=1);

namespace Unicom360\Http;

/**
 * One HTTP request, as the client hands it to a Transport.
 *
 * The X-API-Key header holds the API key: a transport must never log headers.
 */
final class Request
{
    /**
     * @param string $method GET or POST
     * @param string $url the absolute URL, base URL included
     * @param array<string, string> $headers header name to value
     * @param string|null $body the JSON body, or null for a request without one
     * @param float $timeout seconds from sending the request to receiving the whole body
     * @param float $connectTimeout seconds to establish the connection, TLS included
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
        public readonly ?string $body,
        public readonly float $timeout,
        public readonly float $connectTimeout,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $headers = $this->headers;
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'x-api-key' || strtolower($name) === 'authorization') {
                $headers[$name] = '[REDACTED]';
            }
        }

        return [
            'method' => $this->method,
            'url' => $this->url,
            'headers' => $headers,
            'body' => $this->body === null ? null : '[' . \strlen($this->body) . ' bytes]',
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
        ];
    }
}
