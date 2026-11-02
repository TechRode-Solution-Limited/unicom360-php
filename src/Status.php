<?php

declare(strict_types=1);

namespace Unicom360;

/**
 * Status values the gateway sends today. Statuses are open strings: models pass through any
 * value, so compare with these constants and keep a default branch.
 *
 * Webhook delivery statuses are lower-case; see Unicom360\Webhook\DeliveryStatus.
 */
final class Status
{
    // Send results (SMS, email, WhatsApp templates and sessions).
    public const ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const INVALID_MOBILE_NUMBER = 'Invalid mobile number';
    public const INVALID_EMAIL_ADDRESS = 'Invalid email address';
    public const REJECTED = 'REJECTED';

    // WhatsApp message status ($client->whatsapp->status()).
    public const ACCEPTED = 'ACCEPTED';
    public const SENT = 'SENT';
    public const DELIVERED = 'DELIVERED';
    public const READ = 'READ';
    public const FAILED = 'FAILED';
    public const NOT_FOUND = 'NOT_FOUND';

    // SMS delivery reports (unsigned callbacks).
    public const UNKNOWN_ERROR = 'UNKNOWN_ERROR';

    private function __construct() {}
}
