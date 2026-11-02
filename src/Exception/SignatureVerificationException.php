<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * A webhook delivery failed verification. getReason() says which check failed; answer the
 * delivery with HTTP 401 (or 400) and do not process it.
 */
class SignatureVerificationException extends Unicom360Exception
{
    public const TYPE = 'signature_verification_error';

    public const MISSING_SIGNATURE = 'missing_signature';
    public const MISSING_TIMESTAMP = 'missing_timestamp';
    public const INVALID_TIMESTAMP = 'invalid_timestamp';
    public const MALFORMED_SIGNATURE = 'malformed_signature';
    public const TIMESTAMP_OUT_OF_TOLERANCE = 'timestamp_out_of_tolerance';
    public const SIGNATURE_MISMATCH = 'signature_mismatch';

    private const MESSAGES = [
        self::MISSING_SIGNATURE => 'the X-Unicom360-Signature header is missing or empty',
        self::MISSING_TIMESTAMP => 'the X-Unicom360-Timestamp header is missing or empty',
        self::INVALID_TIMESTAMP => 'the X-Unicom360-Timestamp header is not a whole number of seconds',
        self::MALFORMED_SIGNATURE => 'the X-Unicom360-Signature header is not "sha256=" followed by 64 lowercase hex digits',
        self::TIMESTAMP_OUT_OF_TOLERANCE => 'the X-Unicom360-Timestamp header is too far from the current time',
        self::SIGNATURE_MISMATCH => 'the signature does not match the body and the secret',
    ];

    public function __construct(private readonly string $reason)
    {
        parent::__construct('Webhook signature verification failed: ' . (self::MESSAGES[$reason] ?? $reason) . " ({$reason})");
    }

    /**
     * Which check failed: missing_signature, missing_timestamp, invalid_timestamp,
     * malformed_signature, timestamp_out_of_tolerance or signature_mismatch.
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
