<?php

declare(strict_types=1);

namespace Unicom360;

/**
 * Normalised error codes, as returned by getErrorCode() on ApiException and
 * ApiStatusException.
 *
 * Codes are open: the gateway may add new ones, and any upper-case code it sends passes through
 * unchanged. Compare with these constants rather than switching exhaustively.
 */
final class ErrorCode
{
    public const UNKNOWN = 'UNKNOWN';

    // SMS and email refusals.
    public const INVALID_SENDER_ID = 'INVALID_SENDER_ID';
    public const SENDER_ID_NOT_ALLOWED = 'SENDER_ID_NOT_ALLOWED';
    public const INVALID_RECIPIENT_LIMIT = 'INVALID_RECIPIENT_LIMIT';
    public const INSUFFICIENT_BUNDLE = 'INSUFFICIENT_BUNDLE';
    public const DUPLICATE_REFERENCE = 'DUPLICATE_REFERENCE';
    public const INVALID_PARAMETERS = 'INVALID_PARAMETERS';

    // WhatsApp refusals.
    public const SENDER_REQUIRED = 'SENDER_REQUIRED';
    public const TEMPLATE_NOT_FOUND = 'TEMPLATE_NOT_FOUND';
    public const TEMPLATE_NOT_APPROVED = 'TEMPLATE_NOT_APPROVED';
    public const LANGUAGE_REQUIRED = 'LANGUAGE_REQUIRED';
    public const TEMPLATE_SENDER_MISMATCH = 'TEMPLATE_SENDER_MISMATCH';
    public const NO_VALID_RECIPIENTS = 'NO_VALID_RECIPIENTS';
    public const SEND_FAILED = 'SEND_FAILED';
    public const SESSION_WINDOW_CLOSED = 'SESSION_WINDOW_CLOSED';
    public const REPLY_TO_NOT_FOUND = 'REPLY_TO_NOT_FOUND';
    public const REACTION_TARGET_NOT_FOUND = 'REACTION_TARGET_NOT_FOUND';
    public const INVALID_NUMBER = 'INVALID_NUMBER';
    public const INVALID_USER_REFERENCE_ID = 'INVALID_USER_REFERENCE_ID';
    public const MEDIA_URL_INVALID = 'MEDIA_URL_INVALID';
    public const CLIENT_NOT_FOUND = 'CLIENT_NOT_FOUND';

    // Authentication (HTTP 401) and rate limiting (HTTP 429).
    public const MISSING_API_KEY = 'MISSING_API_KEY';
    public const INVALID_AUTHORIZATION = 'INVALID_AUTHORIZATION';
    public const AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
    public const CREDENTIAL_DISABLED = 'CREDENTIAL_DISABLED';
    public const CLIENT_DISABLED = 'CLIENT_DISABLED';
    public const CREDENTIAL_EXPIRED = 'CREDENTIAL_EXPIRED';
    public const IP_NOT_ALLOWED = 'IP_NOT_ALLOWED';
    public const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';

    /**
     * Gateway sentences mapped to their code. Matching is exact and case-sensitive.
     */
    private const ALIASES = [
        'Invalid From Sender ID' => self::INVALID_SENDER_ID,
        'Invalid Sender' => self::INVALID_SENDER_ID,
        'From Sender ID not allowed' => self::SENDER_ID_NOT_ALLOWED,
        'Sender not allowed' => self::SENDER_ID_NOT_ALLOWED,
        'Recipient list size is invalid' => self::INVALID_RECIPIENT_LIMIT,
        'Invalid Recipient Limit' => self::INVALID_RECIPIENT_LIMIT,
        'INSUFFICIENT_BUNDLE: no international SMS bundle' => self::INSUFFICIENT_BUNDLE,
        'Insufficient bundle' => self::INSUFFICIENT_BUNDLE,
        'INSUFFICIENT_BUNDLE_LIMIT' => self::INSUFFICIENT_BUNDLE,
        'duplicate user reference id' => self::DUPLICATE_REFERENCE,
        'DUPLICATE_MSG_REF' => self::DUPLICATE_REFERENCE,
        'Invalid parameters provided' => self::INVALID_PARAMETERS,
        'Missing API key. Provide X-API-Key header or Authorization: Bearer <api_key>' => self::MISSING_API_KEY,
        "Invalid authorization format. Use 'Bearer <api_key>' or 'Basic <base64>'" => self::INVALID_AUTHORIZATION,
        'Invalid Basic auth format. Use api_key as username' => self::INVALID_AUTHORIZATION,
        'Invalid Base64 encoding in Basic auth' => self::INVALID_AUTHORIZATION,
    ];

    private function __construct() {}

    /**
     * Normalises a gateway message to a code.
     *
     * The first rule that matches wins: an exact sentence from the alias table; a whole message
     * that is an upper-case code (aliased if listed, else passed through); a message starting
     * with an upper-case code, a colon and whitespace (the code, aliased if listed); otherwise
     * UNKNOWN. Nothing is trimmed and non-strings are UNKNOWN.
     */
    public static function normalize(mixed $message): string
    {
        if (!\is_string($message)) {
            return self::UNKNOWN;
        }
        if (\array_key_exists($message, self::ALIASES)) {
            return self::ALIASES[$message];
        }
        if (preg_match('/\A[A-Z][A-Z0-9_]*\z/', $message) === 1) {
            return $message;
        }
        if (preg_match('/\A([A-Z][A-Z0-9_]*):\s/', $message, $m) === 1) {
            return self::ALIASES[$m[1]] ?? $m[1];
        }

        return self::UNKNOWN;
    }
}
