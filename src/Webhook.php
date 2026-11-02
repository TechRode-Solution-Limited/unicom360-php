<?php

declare(strict_types=1);

namespace Unicom360;

use Unicom360\Exception\SignatureVerificationException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Internal\Json;
use Unicom360\Internal\Values;
use Unicom360\Webhook\EmailDeliveryReport;
use Unicom360\Webhook\Event;
use Unicom360\Webhook\MessageReceivedEvent;
use Unicom360\Webhook\MessageStatusEvent;
use Unicom360\Webhook\SmsDeliveryReport;
use Unicom360\Webhook\TestEvent;
use Unicom360\Webhook\UnknownEvent;

/**
 * Verifies and parses webhook deliveries. No client or API key is needed.
 *
 * WhatsApp events (message.status, message.received, webhook.test) are signed:
 *
 *     $event = Webhook::constructEvent(file_get_contents('php://input'), getallheaders(), $secret);
 *
 * SMS and email delivery reports are not signed and never retried: anyone can post one, so
 * treat them as hints and confirm anything important another way.
 */
final class Webhook
{
    public const SIGNATURE_HEADER = 'X-Unicom360-Signature';
    public const TIMESTAMP_HEADER = 'X-Unicom360-Timestamp';
    public const EVENT_HEADER = 'X-Unicom360-Event';
    public const DELIVERY_HEADER = 'X-Unicom360-Delivery';

    /** The default clock skew allowed, in seconds. */
    public const DEFAULT_TOLERANCE = 300;

    private function __construct() {}

    /**
     * Checks a delivery's signature. Returns nothing; throws SignatureVerificationException with
     * a reason when the delivery must be rejected.
     *
     * @param string $rawBody the exact bytes received, before any JSON parsing
     *                        (file_get_contents('php://input'), $request->getContent())
     * @param array<array-key, mixed>|object $headers the request headers: an array of name to
     *                                                value (or list of values), $_SERVER, a
     *                                                PSR-7 message or a Symfony/Laravel HeaderBag;
     *                                                names are case-insensitive
     * @param string $secret the endpoint's signing secret from the portal, used as is (never
     *                       base64-decoded)
     * @param int $tolerance the clock skew allowed, in seconds
     * @param int|float|null $now the current Unix time (defaults to the system clock)
     *
     * @throws SignatureVerificationException
     */
    public static function verify(
        string $rawBody,
        array|object $headers,
        #[\SensitiveParameter]
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        int|float|null $now = null,
    ): void {
        $signature = self::header($headers, self::SIGNATURE_HEADER);
        if ($signature === null || $signature === '') {
            throw new SignatureVerificationException(SignatureVerificationException::MISSING_SIGNATURE);
        }
        $timestamp = self::header($headers, self::TIMESTAMP_HEADER);
        if ($timestamp === null || $timestamp === '') {
            throw new SignatureVerificationException(SignatureVerificationException::MISSING_TIMESTAMP);
        }
        if (preg_match('/\A[0-9]+\z/', $timestamp) !== 1) {
            throw new SignatureVerificationException(SignatureVerificationException::INVALID_TIMESTAMP);
        }
        if (preg_match('/\Asha256=[0-9a-f]{64}\z/', $signature) !== 1) {
            throw new SignatureVerificationException(SignatureVerificationException::MALFORMED_SIGNATURE);
        }
        $now ??= microtime(true);
        if (!self::withinTolerance($timestamp, (float) $now, $tolerance)) {
            throw new SignatureVerificationException(SignatureVerificationException::TIMESTAMP_OUT_OF_TOLERANCE);
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        if (!hash_equals($expected, substr($signature, 7))) {
            throw new SignatureVerificationException(SignatureVerificationException::SIGNATURE_MISMATCH);
        }
    }

    /**
     * Verifies a delivery, then parses it into MessageStatusEvent, MessageReceivedEvent,
     * TestEvent or (for an event this SDK does not know yet) UnknownEvent.
     *
     * Deliveries are retried on any non-2xx answer, so answer 2xx quickly and drop repeats by
     * the event's deliveryId.
     *
     * @param array<array-key, mixed>|object $headers see verify()
     *
     * @throws SignatureVerificationException when verification fails
     * @throws Unicom360Exception when the body is not a JSON object with a string "event"
     */
    public static function constructEvent(
        string $rawBody,
        array|object $headers,
        #[\SensitiveParameter]
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        int|float|null $now = null,
    ): Event {
        self::verify($rawBody, $headers, $secret, $tolerance, $now);

        $json = Json::decodeObject($rawBody);
        $event = $json === null ? null : Values::string($json, 'event');
        if ($json === null || $event === null) {
            throw new Unicom360Exception('The webhook body is not a JSON object with a string "event".');
        }
        $deliveryId = self::header($headers, self::DELIVERY_HEADER);

        return match ($event) {
            MessageStatusEvent::TYPE => MessageStatusEvent::fromArray($json, $deliveryId),
            MessageReceivedEvent::TYPE => MessageReceivedEvent::fromArray($json, $deliveryId),
            TestEvent::TYPE => TestEvent::fromArray($json, $deliveryId),
            default => UnknownEvent::fromArray($json, $deliveryId),
        };
    }

    /**
     * Parses an SMS delivery report (posted to the send's callback_url). Unsigned: treat it as
     * a hint.
     *
     * @throws Unicom360Exception when the body is not a JSON object
     */
    public static function parseSmsCallback(string $rawBody): SmsDeliveryReport
    {
        $json = Json::decodeObject($rawBody);
        if ($json === null) {
            throw new Unicom360Exception('The SMS delivery report is not a JSON object.');
        }

        return SmsDeliveryReport::fromArray($json);
    }

    /**
     * Parses an email delivery report (posted to the send's callback_url). Unsigned: treat it
     * as a hint. Its numeric message_id becomes a string.
     *
     * @throws Unicom360Exception when the body is not a JSON object
     */
    public static function parseEmailCallback(string $rawBody): EmailDeliveryReport
    {
        $json = Json::decodeObject($rawBody);
        if ($json === null) {
            throw new Unicom360Exception('The email delivery report is not a JSON object.');
        }

        return EmailDeliveryReport::fromArray($json);
    }

    /**
     * Reads one header (first value) from any common header collection.
     *
     * @param array<array-key, mixed>|object $headers
     */
    public static function header(array|object $headers, string $name): ?string
    {
        $lower = strtolower($name);
        if (\is_object($headers)) {
            if (method_exists($headers, 'getHeader')) {
                // PSR-7 MessageInterface: a list of values.
                return self::first($headers->getHeader($name));
            }
            if (method_exists($headers, 'get')) {
                // Symfony and Laravel HeaderBag.
                return self::first($headers->get($name));
            }
            if (!$headers instanceof \Traversable) {
                return null;
            }
            $headers = iterator_to_array($headers);
        }

        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        foreach ($headers as $key => $value) {
            if (\is_string($key) && (strtolower($key) === $lower || $key === $server)) {
                return self::first($value);
            }
        }

        return null;
    }

    private static function first(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = $value === [] ? null : reset($value);
        }

        return \is_string($value) ? $value : null;
    }

    private static function withinTolerance(string $timestamp, float $now, int $tolerance): bool
    {
        $digits = ltrim($timestamp, '0');
        // Larger than a 64-bit integer: out of tolerance.
        if (\strlen($digits) > 19 || (\strlen($digits) === 19 && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
            return false;
        }

        return abs($now - (float) $digits) <= $tolerance;
    }
}
