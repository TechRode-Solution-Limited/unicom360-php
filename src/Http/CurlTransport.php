<?php

declare(strict_types=1);

namespace Unicom360\Http;

/**
 * The default transport, on ext-curl.
 *
 * It never follows redirects (a 3xx is returned as is, so the API key is never replayed to
 * another host), verifies TLS certificates, accepts compressed responses and reuses one
 * connection across requests. Extra cURL options (a proxy, a CA bundle) can be passed to the
 * constructor; they are applied after the defaults.
 */
final class CurlTransport implements Transport
{
    /**
     * cURL errors that happen before any byte of the request is written: resolving, connecting
     * and the TLS handshake.
     */
    private const PRE_SEND_ERRORS = [
        1,  // CURLE_UNSUPPORTED_PROTOCOL
        3,  // CURLE_URL_MALFORMAT
        5,  // CURLE_COULDNT_RESOLVE_PROXY
        6,  // CURLE_COULDNT_RESOLVE_HOST
        7,  // CURLE_COULDNT_CONNECT
        35, // CURLE_SSL_CONNECT_ERROR
        51, // CURLE_PEER_FAILED_VERIFICATION (older libcurl)
        53, // CURLE_SSL_ENGINE_NOTFOUND
        54, // CURLE_SSL_ENGINE_SETFAILED
        58, // CURLE_SSL_CERTPROBLEM
        59, // CURLE_SSL_CIPHER
        60, // CURLE_PEER_FAILED_VERIFICATION
        64, // CURLE_USE_SSL_FAILED
        66, // CURLE_SSL_ENGINE_INITFAILED
        77, // CURLE_SSL_CACERT_BADFILE
        83, // CURLE_SSL_ISSUER_ERROR
        90, // CURLE_SSL_PINNEDPUBKEYNOTMATCH
        91, // CURLE_SSL_INVALIDCERTSTATUS
    ];

    private const TIMEOUT_ERROR = 28; // CURLE_OPERATION_TIMEDOUT

    private ?\CurlHandle $handle = null;

    /**
     * @param array<int, mixed> $curlOptions extra CURLOPT_* options, applied after the defaults
     */
    public function __construct(private readonly array $curlOptions = []) {}

    public function send(Request $request): Response
    {
        $handle = $this->handle();

        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        // Without this, cURL sends "Expect: 100-continue" for larger bodies and waits for it.
        $headerLines[] = 'Expect:';

        $responseHeaders = [];
        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT_MS => max(1, (int) round($request->timeout * 1000)),
            CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) round($request->connectTimeout * 1000)),
            CURLOPT_NOSIGNAL => true,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $ch, string $line) use (&$responseHeaders): int {
                $length = \strlen($line);
                $line = trim($line);
                if (str_starts_with($line, 'HTTP/')) {
                    // A new status line (after a 1xx): drop the previous block.
                    $responseHeaders = [];
                } elseif ($line !== '' && str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))][] = trim($value);
                }

                return $length;
            },
        ];
        if ($request->method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $request->method;
            if ($request->method === 'POST') {
                $options[CURLOPT_POST] = true;
            }
            $options[CURLOPT_POSTFIELDS] = $request->body ?? '';
        }

        curl_reset($handle);
        foreach ($this->curlOptions as $option => $value) {
            $options[$option] = $value;
        }
        if (!curl_setopt_array($handle, $options)) {
            throw new TransportException('Could not configure the cURL request', false, false);
        }

        $body = curl_exec($handle);
        if (!\is_string($body)) {
            throw $this->failure($handle);
        }

        /** @var array<string, list<string>> $responseHeaders */
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new Response(\is_int($status) ? $status : 0, $responseHeaders, $body);
    }

    private function handle(): \CurlHandle
    {
        if ($this->handle === null) {
            $handle = curl_init();
            if ($handle === false) {
                throw new TransportException('Could not initialise cURL', false, false);
            }
            $this->handle = $handle;
        }

        return $this->handle;
    }

    private function failure(\CurlHandle $handle): TransportException
    {
        $errno = curl_errno($handle);
        $message = 'cURL error ' . $errno . ': ' . curl_error($handle);
        // Drop the handle: its connection may be in an unknown state.
        $this->handle = null;

        if ($errno === self::TIMEOUT_ERROR) {
            return new TransportException($message, $this->connected($handle), true);
        }
        if (\in_array($errno, self::PRE_SEND_ERRORS, true)) {
            return new TransportException($message, false, false);
        }

        // A reset, an empty reply, a send or receive failure: the request may have arrived.
        return new TransportException($message, true, false);
    }

    /**
     * Whether the connection (TLS included) was established, after which bytes of the request
     * may have been written.
     */
    private function connected(\CurlHandle $handle): bool
    {
        $pretransfer = curl_getinfo($handle, CURLINFO_PRETRANSFER_TIME);
        $requestSize = curl_getinfo($handle, CURLINFO_REQUEST_SIZE);

        return (\is_float($pretransfer) && $pretransfer > 0.0) || (\is_int($requestSize) && $requestSize > 0);
    }
}
