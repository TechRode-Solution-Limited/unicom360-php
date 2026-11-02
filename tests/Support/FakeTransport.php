<?php

declare(strict_types=1);

namespace Unicom360\Tests\Support;

use Unicom360\Http\Request;
use Unicom360\Http\Response;
use Unicom360\Http\Transport;
use Unicom360\Http\TransportException;

/**
 * A transport that records every request and answers from a script: one step per attempt,
 * each a Response, a TransportException to throw, or a fixture step array
 * ({"http": {...}} or {"transport": kind}).
 */
final class FakeTransport implements Transport
{
    /** @var list<Request> */
    public array $requests = [];

    /**
     * @param list<Response|\Exception|array<mixed>> $steps
     * @param Response|null $fallback answers every attempt past the script, when set
     */
    public function __construct(
        private array $steps = [],
        private readonly ?Response $fallback = null,
    ) {}

    /**
     * A transport that answers every attempt with the same fixture response.
     *
     * @param array<mixed> $http {status, headers, body_text}
     */
    public static function always(array $http): self
    {
        return new self([], self::response($http));
    }

    public function send(Request $request): Response
    {
        $attempt = \count($this->requests);
        $this->requests[] = $request;
        $step = $this->steps[$attempt] ?? null;
        if ($step === null) {
            if ($this->fallback !== null) {
                return $this->fallback;
            }
            throw new \LogicException('The client made attempt ' . ($attempt + 1) . ' but the script has no step for it.');
        }
        if ($step instanceof Response) {
            return $step;
        }
        if ($step instanceof \Exception) {
            throw $step;
        }
        if (isset($step['http'])) {
            return self::response(Fx::arr($step, 'http'));
        }

        throw self::failure(Fx::str($step, 'transport'));
    }

    public function lastRequest(): Request
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new \LogicException('No request was sent.');
        }

        return $last;
    }

    /**
     * @param array<mixed> $http
     */
    public static function response(array $http): Response
    {
        $headers = [];
        foreach (Fx::arr($http, 'headers') as $name => $value) {
            if (\is_string($value)) {
                $headers[(string) $name] = $value;
            }
        }

        return new Response(Fx::int($http, 'status'), $headers, Fx::str($http, 'body_text'));
    }

    /**
     * The transport failure a retries.json step names (conformance/README.md, retries.json).
     */
    public static function failure(string $kind): TransportException
    {
        return match ($kind) {
            'connect_refused' => new TransportException('connection refused', false, false),
            'dns_failure' => new TransportException('could not resolve host', false, false),
            'timeout_after_send' => new TransportException('response timed out', true, true),
            'reset_after_send' => new TransportException('connection reset by peer', true, false),
            default => throw new \LogicException("Unknown transport step kind {$kind}"),
        };
    }
}
