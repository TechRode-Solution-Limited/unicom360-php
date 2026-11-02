<?php

declare(strict_types=1);

namespace Unicom360\Tests\Support;

use Unicom360\Client;
use Unicom360\Model\Balance;
use Unicom360\Model\Balances;
use Unicom360\Model\EmailSendResult;
use Unicom360\Model\Health;
use Unicom360\Model\SessionSendResult;
use Unicom360\Model\SmsSendResult;
use Unicom360\Model\StatusResult;
use Unicom360\Model\TemplateList;
use Unicom360\Model\TemplateSendResult;
use Unicom360\WhatsApp\ContactCard;
use Unicom360\WhatsApp\ListRow;
use Unicom360\WhatsApp\ListSection;
use Unicom360\WhatsApp\ReplyButton;
use Unicom360\WhatsApp\Session;
use Unicom360\WhatsApp\SessionMessage;
use Unicom360\WhatsApp\TemplateBody;
use Unicom360\WhatsApp\TemplateButton;
use Unicom360\WhatsApp\TemplateHeader;
use Unicom360\WhatsApp\TemplateRecipient;

/**
 * Maps a canonical operation name and its fixture params (API names) to the SDK's public API,
 * as conformance/README.md ("Operations") describes.
 */
final class Harness
{
    public const OPERATIONS = [
        'sms.send',
        'email.send',
        'whatsapp.send_template',
        'whatsapp.send_session',
        'whatsapp.status',
        'whatsapp.templates',
        'balance.get',
        'balance.all',
        'health',
    ];

    public const API_KEY = 'test-api-key';

    /**
     * A client for the harness: the test key, the default base URL and the given seams.
     *
     * @param (callable(): (float|int))|null $clock
     * @param (callable(float): mixed)|null $sleeper
     * @param (callable(): (float|int))|null $random
     */
    public static function client(
        FakeTransport $transport,
        int $maxRetries = 0,
        float $maxRetryWait = 60.0,
        ?callable $clock = null,
        ?callable $sleeper = null,
        ?callable $random = null,
        bool $autoReferenceIds = false,
    ): Client {
        return new Client(
            apiKey: self::API_KEY,
            baseUrl: Client::DEFAULT_BASE_URL,
            maxRetries: $maxRetries,
            maxRetryWait: $maxRetryWait,
            autoReferenceIds: $autoReferenceIds,
            transport: $transport,
            clock: $clock ?? static fn(): float => 1767225630.0,
            sleeper: $sleeper ?? static function (float $seconds): void {
                throw new \LogicException("The client tried to sleep {$seconds}s in a test that expects no retry.");
            },
            random: $random,
        );
    }

    /**
     * Calls an operation through its typed, named-argument API.
     *
     * @param array<mixed> $params
     */
    public static function call(Client $client, string $operation, array $params): object
    {
        return match ($operation) {
            'sms.send' => $client->sms->send(
                from: Fx::str($params, 'from'),
                to: Fx::strOrList($params, 'to'),
                message: Fx::str($params, 'message'),
                userReferenceId: Fx::optStr($params, 'user_reference_id'),
                callbackUrl: Fx::optStr($params, 'callback_url'),
                source: Fx::optStr($params, 'source'),
            ),
            'email.send' => $client->email->send(
                from: Fx::str($params, 'from'),
                to: Fx::strOrList($params, 'to'),
                subject: Fx::str($params, 'subject'),
                emailBody: Fx::str($params, 'email_body'),
                replyTo: Fx::optStr($params, 'reply_to'),
                userReferenceId: Fx::optStr($params, 'user_reference_id'),
                callbackUrl: Fx::optStr($params, 'callback_url'),
                source: Fx::optStr($params, 'source'),
            ),
            'whatsapp.send_template' => $client->whatsapp->sendTemplate(
                template: Fx::str($params, 'template'),
                recipients: array_map(
                    static fn(mixed $r): TemplateRecipient => self::recipient(Fx::asArray($r, 'recipient')),
                    Fx::list($params, 'recipients'),
                ),
                from: Fx::optStr($params, 'from'),
                language: Fx::optStr($params, 'language'),
                callbackUrl: Fx::optStr($params, 'callback_url'),
            ),
            'whatsapp.send_session' => $client->whatsapp->sendSession(self::session($params)),
            'whatsapp.status' => $client->whatsapp->status(Fx::strings($params, 'message_ids')),
            'whatsapp.templates' => self::noParams($params, $client->whatsapp->templates(...)),
            'balance.get' => $client->balance->get(Fx::optStr($params, 'bundle_type')),
            'balance.all' => self::noParams($params, $client->balance->all(...)),
            'health' => self::noParams($params, $client->health(...)),
            default => throw new \LogicException("Unknown operation {$operation}"),
        };
    }

    /**
     * Calls a send operation with one array keyed by API names (the dynamic form).
     *
     * @param array<mixed> $params
     */
    public static function callWithArray(Client $client, string $operation, array $params): object
    {
        /** @var array<string, mixed> $params */
        return match ($operation) {
            'sms.send' => $client->sms->send($params),
            'email.send' => $client->email->send($params),
            'whatsapp.send_template' => $client->whatsapp->sendTemplate($params),
            'whatsapp.send_session' => $client->whatsapp->sendSession($params),
            default => throw new \LogicException("Operation {$operation} has no array form"),
        };
    }

    /**
     * A template recipient built with the TemplateHeader, TemplateBody and TemplateButton
     * factories.
     *
     * @param array<mixed> $r
     */
    public static function recipient(array $r): TemplateRecipient
    {
        Fx::assertKnownKeys($r, ['to', 'user_reference_id', 'header', 'body', 'buttons', 'otp'], 'recipient');
        $header = null;
        if (isset($r['header'])) {
            if (\is_string($r['header'])) {
                $header = TemplateHeader::text($r['header']);
            } else {
                $h = Fx::arr($r, 'header');
                $header = match (Fx::str($h, 'type')) {
                    'image' => TemplateHeader::image(Fx::str($h, 'url')),
                    'video' => TemplateHeader::video(Fx::str($h, 'url')),
                    'document' => TemplateHeader::document(Fx::str($h, 'url'), Fx::optStr($h, 'filename')),
                    default => throw new \LogicException('Unknown header type'),
                };
            }
        }
        $body = null;
        if (isset($r['body'])) {
            $b = Fx::arr($r, 'body');
            if (array_is_list($b)) {
                $body = TemplateBody::of(Fx::strings($r, 'body'));
            } else {
                $named = [];
                foreach ($b as $name => $value) {
                    $named[(string) $name] = Fx::str($b, (string) $name);
                }
                $body = TemplateBody::named($named);
            }
        }
        $buttons = null;
        if (isset($r['buttons'])) {
            $buttons = [];
            foreach (Fx::list($r, 'buttons') as $button) {
                $button = Fx::asArray($button, 'button');
                $buttons[] = match (Fx::str($button, 'type')) {
                    'url' => TemplateButton::url(Fx::int($button, 'index'), Fx::str($button, 'value')),
                    'quick_reply' => TemplateButton::quickReply(Fx::int($button, 'index'), Fx::str($button, 'value')),
                    default => throw new \LogicException('Unknown button type'),
                };
            }
        }

        return new TemplateRecipient(
            to: Fx::str($r, 'to'),
            userReferenceId: Fx::optStr($r, 'user_reference_id'),
            header: $header,
            body: $body,
            buttons: $buttons,
            otp: Fx::optStr($r, 'otp'),
        );
    }

    /**
     * A session message built with the factory for params.type, the common fields set from
     * params.
     *
     * @param array<mixed> $p
     */
    public static function session(array $p): SessionMessage
    {
        $to = Fx::str($p, 'to');
        $from = Fx::optStr($p, 'from');
        $replyTo = Fx::optStr($p, 'reply_to');
        $reference = Fx::optStr($p, 'user_reference_id');
        $callbackUrl = Fx::optStr($p, 'callback_url');
        $type = Fx::str($p, 'type');
        $common = ['to', 'type', 'from', 'reply_to', 'user_reference_id', 'callback_url'];

        switch ($type) {
            case 'text':
                Fx::assertKnownKeys($p, [...$common, 'text'], 'text session');

                return Session::text($to, Fx::str($p, 'text'), $from, $replyTo, $reference, $callbackUrl);
            case 'image':
            case 'video':
            case 'document':
            case 'audio':
            case 'sticker':
                Fx::assertKnownKeys($p, [...$common, 'media'], "{$type} session");
                $media = Fx::arr($p, 'media');
                Fx::assertKnownKeys($media, ['url', 'caption', 'filename'], 'media');
                $url = Fx::str($media, 'url');
                $caption = Fx::optStr($media, 'caption');

                return match ($type) {
                    'image' => Session::image($to, $url, $caption, $from, $replyTo, $reference, $callbackUrl),
                    'video' => Session::video($to, $url, $caption, $from, $replyTo, $reference, $callbackUrl),
                    'document' => Session::document($to, $url, $caption, Fx::optStr($media, 'filename'), $from, $replyTo, $reference, $callbackUrl),
                    'audio' => Session::audio($to, $url, $from, $replyTo, $reference, $callbackUrl),
                    default => Session::sticker($to, $url, $from, $replyTo, $reference, $callbackUrl),
                };
            case 'interactive':
                Fx::assertKnownKeys($p, [...$common, 'interactive'], 'interactive session');
                $i = Fx::arr($p, 'interactive');
                if (Fx::str($i, 'type') === 'button') {
                    Fx::assertKnownKeys($i, ['type', 'header', 'body', 'footer', 'buttons'], 'interactive button');
                    $buttons = array_map(
                        static fn(mixed $b): ReplyButton => new ReplyButton(Fx::str(Fx::asArray($b, 'button'), 'id'), Fx::str(Fx::asArray($b, 'button'), 'title')),
                        Fx::list($i, 'buttons'),
                    );

                    return Session::buttons($to, Fx::str($i, 'body'), $buttons, Fx::optStr($i, 'header'), Fx::optStr($i, 'footer'), $from, $replyTo, $reference, $callbackUrl);
                }
                Fx::assertKnownKeys($i, ['type', 'header', 'body', 'footer', 'button', 'sections'], 'interactive list');
                if (Fx::str($i, 'type') !== 'list') {
                    throw new \LogicException('Unknown interactive type');
                }
                $sections = [];
                foreach (Fx::list($i, 'sections') as $section) {
                    $section = Fx::asArray($section, 'section');
                    $rows = array_map(static function (mixed $row): ListRow {
                        $row = Fx::asArray($row, 'row');

                        return new ListRow(Fx::str($row, 'id'), Fx::str($row, 'title'), Fx::optStr($row, 'description'));
                    }, Fx::list($section, 'rows'));
                    $sections[] = new ListSection($rows, Fx::optStr($section, 'title'));
                }

                return Session::list($to, Fx::str($i, 'body'), Fx::str($i, 'button'), $sections, Fx::optStr($i, 'header'), Fx::optStr($i, 'footer'), $from, $replyTo, $reference, $callbackUrl);
            case 'location':
                Fx::assertKnownKeys($p, [...$common, 'location'], 'location session');
                $l = Fx::arr($p, 'location');
                $lat = $l['latitude'] ?? null;
                $lng = $l['longitude'] ?? null;
                if ((!\is_int($lat) && !\is_float($lat)) || (!\is_int($lng) && !\is_float($lng))) {
                    throw new \LogicException('Location coordinates must be numbers');
                }

                return Session::location($to, $lat, $lng, Fx::optStr($l, 'name'), Fx::optStr($l, 'address'), $from, $replyTo, $reference, $callbackUrl);
            case 'contacts':
                Fx::assertKnownKeys($p, [...$common, 'contacts'], 'contacts session');
                $cards = [];
                foreach (Fx::list($p, 'contacts') as $index => $card) {
                    $card = Fx::asArray($card, 'contact');
                    // Alternate the two accepted forms: a ContactCard and a plain array.
                    /** @var array<string, mixed> $card */
                    $cards[] = $index % 2 === 0 ? new ContactCard($card) : $card;
                }

                return Session::contacts($to, $cards, $from, $replyTo, $reference, $callbackUrl);
            case 'reaction':
                Fx::assertKnownKeys($p, ['to', 'type', 'from', 'user_reference_id', 'callback_url', 'reaction'], 'reaction session');
                $r = Fx::arr($p, 'reaction');

                return Session::reaction($to, Fx::str($r, 'message_id'), Fx::str($r, 'emoji'), $from, $reference, $callbackUrl);
            default:
                throw new \LogicException("Unknown session message type {$type}");
        }
    }

    /**
     * The result model class each operation returns.
     *
     * @return class-string
     */
    public static function resultClass(string $operation): string
    {
        return match ($operation) {
            'sms.send' => SmsSendResult::class,
            'email.send' => EmailSendResult::class,
            'whatsapp.send_template' => TemplateSendResult::class,
            'whatsapp.send_session' => SessionSendResult::class,
            'whatsapp.status' => StatusResult::class,
            'whatsapp.templates' => TemplateList::class,
            'balance.get' => Balance::class,
            'balance.all' => Balances::class,
            'health' => Health::class,
            default => throw new \LogicException("Unknown operation {$operation}"),
        };
    }

    /**
     * The params of the first requests.json vector for an operation: valid parameters for
     * responses.json vectors.
     *
     * @return array<mixed>
     */
    public static function sampleParams(string $operation): array
    {
        foreach (Fx::vectors('requests.json') as [$vector]) {
            if (Fx::str($vector, 'operation') === $operation) {
                return Fx::arr($vector, 'params');
            }
        }

        throw new \LogicException("No requests.json vector for {$operation}");
    }

    /**
     * @param array<mixed> $params
     * @param \Closure(): object $call
     */
    private static function noParams(array $params, \Closure $call): object
    {
        if ($params !== []) {
            throw new \LogicException('This operation takes no params');
        }

        return $call();
    }
}
