<?php

declare(strict_types=1);

namespace Unicom360\Exception;

/**
 * The user_reference_id was already used for this recipient and sender (code
 * DUPLICATE_REFERENCE; the gateway says "duplicate user reference id" or DUPLICATE_MSG_REF).
 *
 * When getAttempts() is greater than 1, an earlier attempt of this same call very probably
 * succeeded: the request timed out or failed after it was sent, the SDK retried it, and the
 * retry was refused because the reference was already taken. Treat the message as sent.
 */
class DuplicateReferenceException extends ApiException
{
    /**
     * The message_id of the original message, when the gateway returned it (WhatsApp session
     * sends). Null otherwise.
     */
    public function getOriginalMessageId(): ?string
    {
        $data = $this->getData();
        if (\is_array($data) && isset($data['message_id']) && \is_string($data['message_id'])) {
            return $data['message_id'];
        }

        return null;
    }
}
