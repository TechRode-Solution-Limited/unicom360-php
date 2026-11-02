<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unicom360\BundleType;
use Unicom360\Client;
use Unicom360\Http\Response;
use Unicom360\Tests\Support\FakeTransport;
use Unicom360\WhatsApp\ContactCard;
use Unicom360\WhatsApp\Session;
use Unicom360\WhatsApp\SessionMessage;
use Unicom360\WhatsApp\TemplateBody;
use Unicom360\WhatsApp\TemplateRecipient;

/**
 * How parameters become request bodies, beyond the conformance vectors.
 */
final class RequestBuildingTest extends TestCase
{
    private const SMS_OK = '{"api_reference":"1","data":[],"message":"successful","success":true}';
    private const TEMPLATE_OK = '{"api_reference":"1","data":{"accepted":0,"rejected":0,"results":[]},"message":"successful","success":true}';
    private const SESSION_OK = '{"api_reference":"1","data":{"message_id":"m"},"message":"successful","success":true}';
    private const BALANCE_OK = '{"status":true,"message":"ok","balance":1}';

    public function testNamedArgumentsOverrideTheArrayAndNullsAreDropped(): void
    {
        $transport = new FakeTransport([new Response(200, [], self::SMS_OK)]);
        $client = new Client(apiKey: 'k', transport: $transport);
        $client->sms->send(['from' => 'A', 'to' => '1', 'message' => 'm', 'callback_url' => null, 'future_field' => 7], message: 'override');

        self::assertSame('{"from":"A","to":"1","message":"override","future_field":7}', $transport->lastRequest()->body);
    }

    public function testTextIsSentUnchanged(): void
    {
        $transport = new FakeTransport([new Response(200, [], self::SMS_OK)]);
        $client = new Client(apiKey: 'k', transport: $transport);
        $client->sms->send(from: 'A', to: ['1'], message: "  Line\r\none \u{20AC} / \"q\"  ");

        $body = json_decode((string) $transport->lastRequest()->body, true);
        self::assertIsArray($body);
        self::assertSame("  Line\r\none \u{20AC} / \"q\"  ", $body['message']);
        self::assertSame(['1'], $body['to']);
    }

    public function testPlainArrayRecipientsAndBodies(): void
    {
        $transport = new FakeTransport([new Response(200, [], self::TEMPLATE_OK)]);
        $client = new Client(apiKey: 'k', transport: $transport);
        $client->whatsapp->sendTemplate(
            template: 't',
            recipients: [
                ['to' => '1', 'header' => 'Ada', 'body' => ['x', 'y']],
                new TemplateRecipient('2', body: ['name' => 'Ada']),
                new TemplateRecipient('3', body: TemplateBody::named(['1' => 'a', '2' => 'b'])),
                new TemplateRecipient('4', body: TemplateBody::named([])),
            ],
        );

        self::assertSame(
            '{"template":"t","recipients":[{"to":"1","header":"Ada","body":["x","y"]},{"to":"2","body":{"name":"Ada"}},{"to":"3","body":{"1":"a","2":"b"}},{"to":"4","body":{}}]}',
            $transport->lastRequest()->body,
        );
    }

    public function testSessionMessageImmutableSetters(): void
    {
        $message = Session::text('260971234567', 'Hi');
        $changed = $message->withFrom('260960123456')->withReplyTo('wamid.X')->withUserReferenceId('r-1')->withCallbackUrl('https://example.com/cb');

        self::assertSame(['to' => '260971234567', 'type' => 'text', 'text' => 'Hi'], $message->toArray());
        self::assertSame('text', $changed->type());
        self::assertSame('260971234567', $changed->to());
        self::assertSame([
            'to' => '260971234567',
            'type' => 'text',
            'text' => 'Hi',
            'from' => '260960123456',
            'reply_to' => 'wamid.X',
            'user_reference_id' => 'r-1',
            'callback_url' => 'https://example.com/cb',
        ], $changed->toArray());
    }

    public function testSessionFromArrayAndContactCard(): void
    {
        $transport = new FakeTransport([], new Response(200, [], self::SESSION_OK));
        $client = new Client(apiKey: 'k', transport: $transport);

        $client->whatsapp->sendSession(SessionMessage::fromArray(['to' => '1', 'type' => 'text', 'text' => 'x']));
        self::assertSame('{"to":"1","type":"text","text":"x"}', $transport->lastRequest()->body);

        $client->whatsapp->sendSession(Session::contacts('1', [
            ContactCard::create('Support', firstName: 'Uni', phones: [['phone' => '+260960123456', 'type' => 'WORK']], org: ['company' => 'Techrode']),
        ]));
        self::assertSame(
            '{"to":"1","type":"contacts","contacts":[{"name":{"formatted_name":"Support","first_name":"Uni"},"phones":[{"phone":"+260960123456","type":"WORK"}],"org":{"company":"Techrode"}}]}',
            $transport->lastRequest()->body,
        );
    }

    public function testLocationKeepsIntegersAndFloats(): void
    {
        $transport = new FakeTransport([], new Response(200, [], self::SESSION_OK));
        $client = new Client(apiKey: 'k', transport: $transport);
        $client->whatsapp->sendSession(Session::location('1', 0, -15.4167));
        self::assertSame('{"to":"1","type":"location","location":{"latitude":0,"longitude":-15.4167}}', $transport->lastRequest()->body);
        $client->whatsapp->sendSession(Session::location('1', 10.0, 0.5));
        self::assertSame('{"to":"1","type":"location","location":{"latitude":10.0,"longitude":0.5}}', $transport->lastRequest()->body);
    }

    public function testBalanceBodies(): void
    {
        $transport = new FakeTransport([], new Response(200, [], self::BALANCE_OK));
        $client = new Client(apiKey: 'k', transport: $transport);
        $client->balance->get();
        self::assertSame('{}', $transport->lastRequest()->body);
        $client->balance->get(BundleType::EMAIL);
        self::assertSame('{"bundle_type":"EMAIL"}', $transport->lastRequest()->body);
        $client->balance->all();
        self::assertSame('{}', $transport->lastRequest()->body);
    }

    public function testStatusSendsIdsAsAList(): void
    {
        $transport = new FakeTransport([new Response(200, [], '{"api_reference":"1","data":{"results":[]},"message":"successful","success":true}')]);
        $client = new Client(apiKey: 'k', transport: $transport);
        $client->whatsapp->status([3 => 'a', 7 => 'a']);
        self::assertSame('{"message_ids":["a","a"]}', $transport->lastRequest()->body);
    }
}
