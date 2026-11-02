<?php

declare(strict_types=1);

namespace Unicom360\Tests\Support;

use Unicom360\Exception\ApiConnectionException;
use Unicom360\Exception\ApiException;
use Unicom360\Exception\ApiStatusException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Model\Balance;
use Unicom360\Model\Balances;
use Unicom360\Model\EmailResult;
use Unicom360\Model\EmailSendResult;
use Unicom360\Model\Health;
use Unicom360\Model\MessageError;
use Unicom360\Model\MessageStatus;
use Unicom360\Model\SessionSendResult;
use Unicom360\Model\SmsResult;
use Unicom360\Model\SmsSendResult;
use Unicom360\Model\StatusResult;
use Unicom360\Model\TemplateButtonInfo;
use Unicom360\Model\TemplateInfo;
use Unicom360\Model\TemplateList;
use Unicom360\Model\TemplateResult;
use Unicom360\Model\TemplateSendResult;
use Unicom360\Webhook\EmailDeliveryReport;
use Unicom360\Webhook\InboundMessage;
use Unicom360\Webhook\MessageStatusData;
use Unicom360\Webhook\SmsDeliveryReport;
use Unicom360\Webhook\TestEventData;

/**
 * Test-only: the canonical form of each model (conformance/README.md, "Canonical form and
 * comparison"), built from the typed fields with the API's names, plus every unknown field the
 * model kept in raw. Not public API.
 */
final class Canonical
{
    private const ENVELOPE = ['api_reference', 'message', 'success', 'data'];

    /**
     * @return array<mixed>
     */
    public static function result(object $result): array
    {
        return match (true) {
            $result instanceof SmsSendResult => self::envelope($result->raw, [], [
                'api_reference' => $result->apiReference,
                'message' => $result->message,
                'results' => array_map(self::smsResult(...), $result->results),
            ]),
            $result instanceof EmailSendResult => self::envelope($result->raw, [], [
                'api_reference' => $result->apiReference,
                'message' => $result->message,
                'results' => array_map(self::emailResult(...), $result->results),
            ]),
            $result instanceof TemplateSendResult => self::envelope($result->raw, ['accepted', 'rejected', 'results'], [
                'api_reference' => $result->apiReference,
                'message' => $result->message,
                'accepted' => $result->acceptedCount,
                'rejected' => $result->rejectedCount,
                'results' => array_map(self::templateResult(...), $result->results),
            ]),
            $result instanceof SessionSendResult => self::envelope($result->raw, ['message_id', 'to', 'user_reference_id', 'status', 'category'], [
                'api_reference' => $result->apiReference,
                'message' => $result->message,
                'message_id' => $result->messageId,
                'to' => $result->to,
                'user_reference_id' => $result->userReferenceId,
                'status' => $result->status,
                'category' => $result->category,
            ]),
            $result instanceof StatusResult => self::envelope($result->raw, ['results'], [
                'api_reference' => $result->apiReference,
                'message' => $result->message,
                'results' => array_map(self::messageStatus(...), $result->results),
            ]),
            $result instanceof TemplateList => self::envelope($result->raw, ['templates'], [
                'api_reference' => $result->apiReference,
                'message' => $result->message,
                'templates' => array_map(self::templateInfo(...), $result->templates),
            ]),
            $result instanceof Balance => self::withUnknown($result->raw, ['status'], [
                'message' => $result->message,
                'balance' => $result->balance,
                'bundle_type' => $result->bundleType,
                'client_id' => $result->clientId,
                'client_name' => $result->clientName,
            ]),
            $result instanceof Balances => self::withUnknown($result->raw, ['status'], [
                'message' => $result->message,
                'balances' => $result->balances,
                'client_id' => $result->clientId,
                'client_name' => $result->clientName,
            ]),
            $result instanceof Health => self::withUnknown($result->raw, [], [
                'status' => $result->status,
                'timestamp' => $result->timestamp,
                'version' => $result->version,
                'environment' => $result->environment,
            ]),
            $result instanceof SmsDeliveryReport => self::withUnknown($result->raw, [], [
                'message_id' => $result->messageId,
                'status' => $result->status,
                'mobile_number' => $result->mobileNumber,
            ]),
            $result instanceof EmailDeliveryReport => self::withUnknown($result->raw, [], [
                'message_id' => $result->messageId,
                'status' => $result->status,
                'email_address' => $result->emailAddress,
                'message' => $result->message,
            ]),
            $result instanceof MessageStatusData => self::withUnknown($result->raw, [], [
                'message_id' => $result->messageId,
                'user_reference_id' => $result->userReferenceId,
                'channel' => $result->channel,
                'to' => $result->to,
                'from' => $result->from,
                'status' => $result->status,
                'timestamp' => $result->timestamp,
                'error' => self::messageError($result->error),
                'category' => $result->category,
                'billable' => $result->billable,
            ]),
            $result instanceof InboundMessage => self::withUnknown($result->raw, [], [
                'message_id' => $result->messageId,
                'channel' => $result->channel,
                'from' => $result->from,
                'to' => $result->to,
                'type' => $result->type,
                'text' => $result->text,
                'media' => $result->media === null ? null : self::withUnknown($result->media->raw, [], [
                    'id' => $result->media->id,
                    'mime_type' => $result->media->mimeType,
                    'filename' => $result->media->filename,
                ]),
                'payload' => $result->payload,
                'reply_to' => $result->replyTo,
                'timestamp' => $result->timestamp,
                'window_expires_at' => $result->windowExpiresAt,
            ]),
            $result instanceof TestEventData => self::withUnknown($result->raw, [], ['message' => $result->message]),
            default => throw new \LogicException('No canonical form for ' . $result::class),
        };
    }

    /**
     * The canonical class name of an exception: Exception becomes Error, a leading Api becomes
     * API.
     */
    public static function className(\Throwable $error): string
    {
        $short = substr($error::class, strrpos($error::class, '\\') + 1);
        $short = (string) preg_replace('/Exception$/', 'Error', $short);

        return (string) preg_replace('/^Api/', 'API', $short);
    }

    /**
     * The normalised code of an error, or null for errors that have none (transport errors).
     */
    public static function errorCode(Unicom360Exception $error): ?string
    {
        if ($error instanceof ApiStatusException || $error instanceof ApiException) {
            return $error->getErrorCode();
        }
        if ($error instanceof ApiConnectionException) {
            return null;
        }

        return null;
    }

    /**
     * Whether two canonical JSON values are equal: nulls dropped, key order ignored, numbers by
     * value, strings and booleans exact.
     */
    public static function equal(mixed $expected, mixed $actual): bool
    {
        return self::same(self::dropNulls($expected), self::dropNulls($actual));
    }

    public static function dropNulls(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::dropNulls(...), $value);
        }
        $out = [];
        foreach ($value as $key => $item) {
            if ($item !== null) {
                $out[$key] = self::dropNulls($item);
            }
        }

        return $out;
    }

    private static function same(mixed $a, mixed $b): bool
    {
        if ((\is_int($a) || \is_float($a)) && (\is_int($b) || \is_float($b))) {
            return (float) $a === (float) $b;
        }
        if (\is_array($a) || \is_array($b)) {
            if (!\is_array($a) || !\is_array($b) || \count($a) !== \count($b)) {
                return false;
            }
            if (array_is_list($a) && array_is_list($b)) {
                foreach ($a as $i => $item) {
                    if (!self::same($item, $b[$i])) {
                        return false;
                    }
                }

                return true;
            }
            foreach ($a as $key => $item) {
                if (!\array_key_exists($key, $b) || !self::same($item, $b[$key])) {
                    return false;
                }
            }

            return true;
        }

        return $a === $b;
    }

    /**
     * @return array<mixed>
     */
    private static function smsResult(SmsResult $r): array
    {
        return self::withUnknown($r->raw, [], [
            'message_id' => $r->messageId,
            'status' => $r->status,
            'mobile_number' => $r->mobileNumber,
        ]);
    }

    /**
     * @return array<mixed>
     */
    private static function emailResult(EmailResult $r): array
    {
        return self::withUnknown($r->raw, [], [
            'message_id' => $r->messageId,
            'status' => $r->status,
            'email_address' => $r->emailAddress,
        ]);
    }

    /**
     * @return array<mixed>
     */
    private static function templateResult(TemplateResult $r): array
    {
        return self::withUnknown($r->raw, [], [
            'message_id' => $r->messageId,
            'to' => $r->to,
            'user_reference_id' => $r->userReferenceId,
            'status' => $r->status,
            'reason' => $r->reason,
            'category' => $r->category,
        ]);
    }

    /**
     * @return array<mixed>
     */
    private static function messageStatus(MessageStatus $s): array
    {
        return self::withUnknown($s->raw, [], [
            'message_id' => $s->messageId,
            'status' => $s->status,
            'user_reference_id' => $s->userReferenceId,
            'to' => $s->to,
            'from' => $s->from,
            'sent_at' => $s->sentAt,
            'delivered_at' => $s->deliveredAt,
            'read_at' => $s->readAt,
            'error' => self::messageError($s->error),
            'category' => $s->category,
            'billable' => $s->billable,
            'refunded' => $s->refunded,
        ]);
    }

    /**
     * @return array<mixed>|null
     */
    private static function messageError(?MessageError $e): ?array
    {
        return $e === null ? null : self::withUnknown($e->raw, [], ['code' => $e->code, 'message' => $e->message]);
    }

    /**
     * @return array<mixed>
     */
    private static function templateInfo(TemplateInfo $t): array
    {
        return self::withUnknown($t->raw, [], [
            'name' => $t->name,
            'language' => $t->language,
            'category' => $t->category,
            'from' => $t->from,
            'header' => $t->header === null ? null : self::withUnknown($t->header->raw, [], [
                'type' => $t->header->type,
                'variables' => $t->header->variables,
            ]),
            'body' => $t->body === null ? null : self::withUnknown($t->body->raw, [], [
                'text' => $t->body->text,
                'variables' => $t->body->variables,
            ]),
            'footer' => $t->footer,
            'buttons' => array_map(static fn(TemplateButtonInfo $b): array => self::withUnknown($b->raw, [], [
                'index' => $b->index,
                'type' => $b->type,
                'text' => $b->text,
                'url' => $b->url,
                'otp_type' => $b->otpType,
            ]), $t->buttons),
            'authentication' => $t->authentication === null ? null : self::withUnknown($t->authentication->raw, [], [
                'otp_type' => $t->authentication->otpType,
                'code_expiration_minutes' => $t->authentication->codeExpirationMinutes,
            ]),
        ]);
    }

    /**
     * A top-level result: the envelope's unknown fields, then data's unknown fields (when data
     * is an object), then the typed fields.
     *
     * @param array<mixed> $raw
     * @param list<string> $knownData
     * @param array<string, mixed> $typed
     *
     * @return array<mixed>
     */
    private static function envelope(array $raw, array $knownData, array $typed): array
    {
        $out = [];
        foreach ($raw as $key => $value) {
            if (!\in_array($key, self::ENVELOPE, true)) {
                $out[$key] = $value;
            }
        }
        $data = $raw['data'] ?? null;
        if (\is_array($data) && !array_is_list($data)) {
            foreach ($data as $key => $value) {
                if (!\in_array($key, $knownData, true)) {
                    $out[$key] = $value;
                }
            }
        }

        return $typed + $out;
    }

    /**
     * The typed fields plus every raw field the model does not type (minus $ignore).
     *
     * @param array<mixed> $raw
     * @param list<string> $ignore
     * @param array<string, mixed> $typed
     *
     * @return array<mixed>
     */
    private static function withUnknown(array $raw, array $ignore, array $typed): array
    {
        $out = $typed;
        foreach ($raw as $key => $value) {
            if (!\array_key_exists($key, $typed) && !\in_array($key, $ignore, true)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
