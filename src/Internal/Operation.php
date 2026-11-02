<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * The nine operations: method, path, how the response is read and when a retry is safe.
 *
 * @internal
 */
final class Operation
{
    /** The response is the result object itself. */
    public const KIND_HEALTH = 'health';
    /** The envelope's flag is "status". */
    public const KIND_BALANCE = 'balance';
    /** The envelope's flag is "success". */
    public const KIND_ENVELOPE = 'envelope';

    /** Always safe to retry. */
    public const RETRY_ALWAYS = 'always';
    /** Safe when the message carries a user_reference_id. */
    public const RETRY_WITH_REFERENCE = 'with-user-reference-id';
    /** Safe when every template recipient carries a user_reference_id. */
    public const RETRY_WITH_RECIPIENT_REFERENCES = 'with-recipient-references';
    /** Never safe (email has no unique index behind its reference). */
    public const RETRY_NEVER = 'never';

    private const TABLE = [
        'sms.send' => ['POST', '/api/v1/json/bulk/sms', self::KIND_ENVELOPE, self::RETRY_WITH_REFERENCE, true],
        'email.send' => ['POST', '/api/v1/json/bulk/email', self::KIND_ENVELOPE, self::RETRY_NEVER, true],
        'whatsapp.send_template' => ['POST', '/api/v1/json/whatsapp/template', self::KIND_ENVELOPE, self::RETRY_WITH_RECIPIENT_REFERENCES, true],
        'whatsapp.send_session' => ['POST', '/api/v1/json/whatsapp/session', self::KIND_ENVELOPE, self::RETRY_WITH_REFERENCE, true],
        'whatsapp.status' => ['POST', '/api/v1/json/whatsapp/status', self::KIND_ENVELOPE, self::RETRY_ALWAYS, true],
        'whatsapp.templates' => ['GET', '/api/v1/json/whatsapp/templates', self::KIND_ENVELOPE, self::RETRY_ALWAYS, true],
        'balance.get' => ['POST', '/api/v1/json/balance', self::KIND_BALANCE, self::RETRY_ALWAYS, true],
        'balance.all' => ['POST', '/api/v1/json/balance/all', self::KIND_BALANCE, self::RETRY_ALWAYS, true],
        'health' => ['GET', '/health', self::KIND_HEALTH, self::RETRY_ALWAYS, false],
    ];

    private function __construct(
        public readonly string $name,
        public readonly string $method,
        public readonly string $path,
        public readonly string $kind,
        public readonly string $retry,
        public readonly bool $authenticated,
    ) {}

    public static function get(string $name): self
    {
        if (!isset(self::TABLE[$name])) {
            throw new \LogicException("Unknown Unicom360 operation {$name}");
        }
        [$method, $path, $kind, $retry, $authenticated] = self::TABLE[$name];

        return new self($name, $method, $path, $kind, $retry, $authenticated);
    }

    /**
     * Whether this call may be retried after a failure that happened once the request was
     * sent (a 5xx, a response timeout, SEND_FAILED), decided from the body as sent.
     *
     * @param array<mixed>|null $body
     */
    public function isRetrySafe(?array $body): bool
    {
        switch ($this->retry) {
            case self::RETRY_ALWAYS:
                return true;
            case self::RETRY_WITH_REFERENCE:
                return $body !== null && self::hasReference($body['user_reference_id'] ?? null);
            case self::RETRY_WITH_RECIPIENT_REFERENCES:
                $recipients = $body['recipients'] ?? null;
                if (!\is_array($recipients) || $recipients === []) {
                    return false;
                }
                foreach ($recipients as $recipient) {
                    if (!\is_array($recipient) || !self::hasReference($recipient['user_reference_id'] ?? null)) {
                        return false;
                    }
                }

                return true;
            default:
                return false;
        }
    }

    /**
     * A reference counts when it is a string with at least one non-whitespace character.
     */
    public static function hasReference(mixed $value): bool
    {
        if (!\is_string($value)) {
            return false;
        }
        $match = preg_match('/[^\s\p{Z}\x{FEFF}]/u', $value);

        return $match === false ? trim($value) !== '' : $match === 1;
    }
}
