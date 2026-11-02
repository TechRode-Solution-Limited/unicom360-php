<?php

declare(strict_types=1);

namespace Unicom360\Internal;

use Unicom360\ErrorCode;
use Unicom360\Exception\ApiConnectionException;
use Unicom360\Exception\ApiException;
use Unicom360\Exception\ApiTimeoutException;
use Unicom360\Exception\ConfigurationException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Http\Request;
use Unicom360\Http\TransportException;

/**
 * Sends an operation's request, retrying as section 10 of the SDK contract allows, and
 * returns the decoded response object or throws the last attempt's error.
 *
 * @internal
 */
final class Requester
{
    public function __construct(private readonly Config $config) {}

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * @param array<mixed>|null $body the request body in API shape (builders allowed), or null
     *                                for a request without one
     *
     * @return array<mixed> the decoded response object
     *
     * @throws Unicom360Exception
     */
    public function request(string $operationName, ?array $body, ?float $timeout = null, ?int $maxRetries = null): array
    {
        $operation = Operation::get($operationName);
        $config = $this->config;

        $maxRetries ??= $config->maxRetries;
        if ($maxRetries < 0) {
            throw new ConfigurationException('maxRetries must be 0 or more.');
        }
        $timeout ??= $config->timeout;
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new ConfigurationException('timeout must be a positive number of seconds.');
        }

        $generated = [];
        if ($body !== null) {
            $normalised = Json::normalize($body);
            $body = \is_array($normalised) ? $normalised : [];
            if ($config->autoReferenceIds) {
                $body = ReferenceIds::apply($operation, $body, $generated);
            }
        }
        $retrySafe = $operation->isRetrySafe($body);

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $config->userAgent,
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($operation->authenticated) {
            $headers['X-API-Key'] = $config->apiKey->reveal();
        }
        $request = new Request(
            $operation->method,
            $config->baseUrl . $operation->path,
            $headers,
            $body === null ? null : Json::encodeBody($body),
            $timeout,
            $config->connectTimeout,
        );

        for ($attempt = 1; ; $attempt++) {
            $context = ['method' => $operation->method, 'path' => $operation->path, 'attempt' => $attempt];
            if ($generated !== []) {
                $context['user_reference_ids'] = $generated;
            }
            $outcome = $this->attempt($operation, $request, $retrySafe, $context);
            if ($outcome->result !== null) {
                return $outcome->result;
            }
            $error = $outcome->error;
            if ($error === null) {
                throw new \LogicException('An attempt produced neither a result nor an error.');
            }
            if (!$outcome->retryable || $attempt > $maxRetries) {
                $error->setAttempts($attempt);

                throw $error;
            }
            if ($outcome->rateLimitedAt !== null) {
                // The window is the fixed UTC minute; no header says when it resets.
                $base = 60 - fmod($outcome->rateLimitedAt, 60);
                if ($base > $config->maxRetryWait) {
                    $error->setAttempts($attempt);

                    throw $error;
                }
                $wait = max(1.0, $base + ($config->random)());
            } else {
                $wait = ($config->random)() * min(8.0, 0.5 * 2 ** $attempt, $config->maxRetryWait);
            }
            $config->log->info('Unicom360 retrying request', [
                'method' => $operation->method,
                'path' => $operation->path,
                'attempt' => $attempt,
                'wait_s' => round($wait, 3),
                'cause' => $outcome->cause,
            ]);
            ($config->sleeper)($wait);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function attempt(Operation $operation, Request $request, bool $retrySafe, array $context): Attempt
    {
        $config = $this->config;
        $started = hrtime(true);

        try {
            $response = $config->transport->send($request);
        } catch (\Exception $e) {
            // A custom transport that throws something other than TransportException cannot
            // say whether the request was sent, so assume it was.
            $sent = $e instanceof TransportException ? $e->sent : true;
            $timeout = $e instanceof TransportException && $e->timeout;
            $config->log->debug('Unicom360 request failed', $context + [
                'status' => null,
                'error' => $timeout ? 'timeout' : 'connection',
                'sent' => $sent,
                'api_reference' => null,
                'duration_ms' => self::elapsedMs($started),
            ]);

            return Attempt::failure(
                $this->connectionError($operation, $e, $timeout),
                !$sent || $retrySafe,
                ($timeout ? 'timeout' : 'connection error') . ($sent ? ' after sending' : ' before sending'),
            );
        }

        $now = ($config->clock)();
        $outcome = Interpreter::interpret($operation, $response, $now);
        $apiReference = \is_array($outcome) ? Values::string($outcome, 'api_reference') : $outcome->getApiReference();
        $config->log->debug('Unicom360 request', $context + [
            'status' => $response->status,
            'api_reference' => $apiReference,
            'duration_ms' => self::elapsedMs($started),
        ]);
        if (\is_array($outcome)) {
            return Attempt::success($outcome);
        }

        $status = $response->status;
        if ($status === 429) {
            // Rejected before any processing: always safe to retry.
            return Attempt::failure($outcome, true, 'HTTP 429', $now);
        }
        if ($status >= 500 && $status <= 599) {
            return Attempt::failure($outcome, $retrySafe, "HTTP {$status}");
        }
        if ($outcome instanceof ApiException && $outcome->getErrorCode() === ErrorCode::SEND_FAILED) {
            return Attempt::failure($outcome, $retrySafe, ErrorCode::SEND_FAILED);
        }

        return Attempt::failure($outcome, false, '');
    }

    private function connectionError(Operation $operation, \Throwable $cause, bool $timeout): ApiConnectionException
    {
        $what = "{$operation->method} {$operation->path}";
        if ($timeout) {
            return new ApiTimeoutException("The request to {$what} timed out: " . $cause->getMessage(), previous: $cause);
        }

        return new ApiConnectionException("The request to {$what} failed: " . $cause->getMessage(), previous: $cause);
    }

    private static function elapsedMs(int|float $started): float
    {
        return round((hrtime(true) - $started) / 1e6, 1);
    }
}
