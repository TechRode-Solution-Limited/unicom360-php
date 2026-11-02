<?php

declare(strict_types=1);

namespace Unicom360\Internal;

use Unicom360\ErrorCode;
use Unicom360\Exception\ApiException;
use Unicom360\Exception\ApiServerException;
use Unicom360\Exception\ApiStatusException;
use Unicom360\Exception\AuthenticationException;
use Unicom360\Exception\DuplicateReferenceException;
use Unicom360\Exception\InsufficientBundleException;
use Unicom360\Exception\InvalidRequestException;
use Unicom360\Exception\NoValidRecipientsException;
use Unicom360\Exception\RateLimitException;
use Unicom360\Exception\SessionWindowClosedException;
use Unicom360\Http\Response;

/**
 * Turns one HTTP response into a result (the decoded JSON object) or an error, following
 * section 7 of the SDK contract.
 *
 * @internal
 */
final class Interpreter
{
    private const API_ERROR_CLASSES = [
        ErrorCode::INSUFFICIENT_BUNDLE => InsufficientBundleException::class,
        ErrorCode::DUPLICATE_REFERENCE => DuplicateReferenceException::class,
        ErrorCode::NO_VALID_RECIPIENTS => NoValidRecipientsException::class,
        ErrorCode::SESSION_WINDOW_CLOSED => SessionWindowClosedException::class,
    ];

    private const REASONS = [
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other',
        304 => 'Not Modified', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 406 => 'Not Acceptable', 408 => 'Request Timeout',
        409 => 'Conflict', 410 => 'Gone', 413 => 'Content Too Large', 415 => 'Unsupported Media Type',
        422 => 'Unprocessable Content', 429 => 'Too Many Requests', 500 => 'Internal Server Error',
        501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    private function __construct() {}

    /**
     * @param float $now the clock when the response arrived (for RateLimitException)
     *
     * @return array<mixed>|ApiStatusException|ApiException
     */
    public static function interpret(Operation $operation, Response $response, float $now): array|ApiStatusException|ApiException
    {
        $json = Json::decodeObject($response->body);
        $status = $response->status;

        if ($status >= 200 && $status <= 299) {
            if ($json === null) {
                return self::unusable($response);
            }
            if ($operation->kind === Operation::KIND_HEALTH) {
                return $json;
            }
            $flag = $operation->kind === Operation::KIND_BALANCE ? ($json['status'] ?? null) : ($json['success'] ?? null);
            if ($flag === true) {
                return $json;
            }
            if ($flag !== false) {
                return self::unusable($response);
            }

            return self::apiError($json, $response);
        }

        return self::statusError($json, $response, $now);
    }

    /**
     * The message text: a string as is, a list of strings joined with "; ", else null.
     */
    public static function messageText(mixed $message): ?string
    {
        if (\is_string($message)) {
            return $message;
        }
        if (\is_array($message) && array_is_list($message)) {
            $parts = [];
            foreach ($message as $part) {
                if (!\is_string($part)) {
                    return null;
                }
                $parts[] = $part;
            }

            return implode('; ', $parts);
        }

        return null;
    }

    private static function unusable(Response $response): ApiStatusException
    {
        return new ApiStatusException(
            "HTTP {$response->status} response without a usable Unicom360 envelope",
            $response->status,
            null,
            $response->body,
            ErrorCode::UNKNOWN,
            null,
            $response->headers,
        );
    }

    /**
     * @param array<mixed> $json
     */
    private static function apiError(array $json, Response $response): ApiException
    {
        $text = self::messageText($json['message'] ?? null);
        $code = ErrorCode::normalize($text);
        $class = self::API_ERROR_CLASSES[$code] ?? ApiException::class;

        return new $class(
            $text ?? "The request was refused (HTTP {$response->status}) without a message",
            $response->status,
            self::apiReference($json),
            $response->body,
            $code,
            $json['data'] ?? null,
            $response->headers,
        );
    }

    /**
     * @param array<mixed>|null $json
     */
    private static function statusError(?array $json, Response $response, float $now): ApiStatusException
    {
        $status = $response->status;
        $generated = 'HTTP ' . $status . (isset(self::REASONS[$status]) ? ' ' . self::REASONS[$status] : '');
        $message = $generated;
        $code = ErrorCode::UNKNOWN;
        $apiReference = null;
        $data = null;

        if ($json !== null && \array_key_exists('message', $json)) {
            $text = self::messageText($json['message']);
            $message = $text ?? $generated;
            $code = ErrorCode::normalize($text);
            $apiReference = self::apiReference($json);
            $data = $json['data'] ?? null;
        } elseif ($json !== null && \is_array($json['errors'] ?? null) && \is_string($json['errors']['detail'] ?? null)) {
            $message = $json['errors']['detail'];
        }

        if ($status === 429) {
            return new RateLimitException(
                $message,
                $status,
                $apiReference,
                $response->body,
                $code,
                $data,
                $response->headers,
                60 - fmod($now, 60),
            );
        }

        $class = match (true) {
            $status === 400 => InvalidRequestException::class,
            $status === 401 => AuthenticationException::class,
            $status >= 500 && $status <= 599 => ApiServerException::class,
            default => ApiStatusException::class,
        };

        return new $class($message, $status, $apiReference, $response->body, $code, $data, $response->headers);
    }

    /**
     * @param array<mixed> $json
     */
    private static function apiReference(array $json): ?string
    {
        $value = $json['api_reference'] ?? null;

        return \is_string($value) ? $value : null;
    }
}
