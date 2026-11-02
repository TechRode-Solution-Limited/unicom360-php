<?php

declare(strict_types=1);

namespace Unicom360\Internal;

/**
 * The autoReferenceIds option: gives every message without a user_reference_id a generated
 * one before the first attempt, so a retry is de-duplicated by the gateway.
 *
 * @internal
 */
final class ReferenceIds
{
    private function __construct() {}

    /**
     * A generated reference: "u360-" followed by a random UUID v4 without dashes.
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return 'u360-' . bin2hex($bytes);
    }

    /**
     * Adds references where none was given. A caller-supplied reference is never replaced.
     *
     * @param array<mixed> $body
     * @param list<string> $generated receives the generated values
     *
     * @return array<mixed>
     */
    public static function apply(Operation $operation, array $body, array &$generated): array
    {
        switch ($operation->name) {
            case 'sms.send':
            case 'email.send':
            case 'whatsapp.send_session':
                if (!isset($body['user_reference_id'])) {
                    $body['user_reference_id'] = $generated[] = self::generate();
                }
                break;
            case 'whatsapp.send_template':
                if (isset($body['recipients']) && \is_array($body['recipients'])) {
                    foreach ($body['recipients'] as $index => $recipient) {
                        if (\is_array($recipient) && !isset($recipient['user_reference_id'])) {
                            $recipient['user_reference_id'] = $generated[] = self::generate();
                            $body['recipients'][$index] = $recipient;
                        }
                    }
                }
                break;
        }

        return $body;
    }
}
