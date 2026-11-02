<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unicom360\Client;
use Unicom360\Exception\ApiTimeoutException;
use Unicom360\Http\Request;
use Unicom360\Http\Response;
use Unicom360\Http\TransportException;
use Unicom360\Tests\Support\FakeTransport;
use Unicom360\Tests\Support\RecordingLogger;
use Unicom360\WhatsApp\Session;
use Unicom360\WhatsApp\TemplateRecipient;

final class AutoReferenceIdsTest extends TestCase
{
    private const PATTERN = '/^u360-[0-9a-f]{32}$/';
    private const SMS_OK = '{"api_reference":"1","data":[],"message":"successful","success":true}';

    public function testOffByDefault(): void
    {
        $transport = new FakeTransport([new Response(200, [], self::SMS_OK)]);
        (new Client(apiKey: 'k', transport: $transport))->sms->send(from: 'A', to: '1', message: 'm');
        self::assertSame('{"from":"A","to":"1","message":"m"}', $transport->lastRequest()->body);
    }

    public function testSmsGetsOneReferenceReusedOnRetry(): void
    {
        $logger = new RecordingLogger();
        $transport = new FakeTransport([
            new TransportException('timed out', true, true),
            new Response(200, [], self::SMS_OK),
        ]);
        $client = $this->client($transport, $logger);
        $client->sms->send(from: 'A', to: '1', message: 'm');

        // Without the generated reference a timeout after sending would not be retried.
        self::assertCount(2, $transport->requests);
        $first = $this->body($transport->requests[0]);
        $second = $this->body($transport->requests[1]);
        self::assertIsString($first['user_reference_id']);
        self::assertMatchesRegularExpression(self::PATTERN, $first['user_reference_id']);
        self::assertSame($first, $second);

        // The generated value is visible in the debug line of the request.
        $debug = $logger->records[0];
        self::assertSame('debug', $debug['level']);
        self::assertSame([$first['user_reference_id']], $debug['context']['user_reference_ids'] ?? null);
    }

    public function testCallerReferenceIsNeverReplaced(): void
    {
        $transport = new FakeTransport([new Response(200, [], self::SMS_OK)]);
        $this->client($transport)->sms->send(from: 'A', to: '1', message: 'm', userReferenceId: 'mine');
        self::assertSame('mine', $this->body($transport->lastRequest())['user_reference_id']);
    }

    public function testEachTemplateRecipientWithoutAReferenceGetsItsOwn(): void
    {
        $ok = '{"api_reference":"1","data":{"accepted":3,"rejected":0,"results":[]},"message":"successful","success":true}';
        $transport = new FakeTransport([new Response(500, [], ''), new Response(200, [], $ok)]);
        $this->client($transport)->whatsapp->sendTemplate(template: 't', recipients: [
            new TemplateRecipient('1'),
            new TemplateRecipient('2', userReferenceId: 'given'),
            ['to' => '3'],
        ]);

        // Every recipient now has a reference, so the 500 was retried.
        self::assertCount(2, $transport->requests);
        $recipients = $this->body($transport->requests[0])['recipients'];
        self::assertIsArray($recipients);
        $refs = array_map(static fn(mixed $r): mixed => \is_array($r) ? ($r['user_reference_id'] ?? null) : null, $recipients);
        self::assertIsString($refs[0]);
        self::assertIsString($refs[2]);
        self::assertMatchesRegularExpression(self::PATTERN, $refs[0]);
        self::assertSame('given', $refs[1]);
        self::assertMatchesRegularExpression(self::PATTERN, $refs[2]);
        self::assertNotSame($refs[0], $refs[2]);
        self::assertSame($this->body($transport->requests[0]), $this->body($transport->requests[1]));
    }

    public function testSessionGetsAReference(): void
    {
        $transport = new FakeTransport([new Response(200, [], '{"api_reference":"1","data":{"message_id":"m"},"message":"successful","success":true}')]);
        $this->client($transport)->whatsapp->sendSession(Session::text('1', 'x'));
        $reference = $this->body($transport->lastRequest())['user_reference_id'];
        self::assertIsString($reference);
        self::assertMatchesRegularExpression(self::PATTERN, $reference);
    }

    public function testEmailGetsAReferenceButIsStillNotRetrySafe(): void
    {
        $transport = new FakeTransport([new TransportException('timed out', true, true)]);
        try {
            $this->client($transport)->email->send(from: 'a@example.com', to: 'b@example.com', subject: 's', emailBody: 'b');
            self::fail('expected a timeout');
        } catch (ApiTimeoutException $e) {
            self::assertSame(1, $e->getAttempts());
        }
        self::assertCount(1, $transport->requests);
        $reference = $this->body($transport->lastRequest())['user_reference_id'];
        self::assertIsString($reference);
        self::assertMatchesRegularExpression(self::PATTERN, $reference);
    }

    public function testReadOperationsAreUntouched(): void
    {
        $transport = new FakeTransport([new Response(200, [], '{"status":true,"message":"ok","balance":1}')]);
        $this->client($transport)->balance->get();
        self::assertSame('{}', $transport->lastRequest()->body);
    }

    private function client(FakeTransport $transport, ?RecordingLogger $logger = null): Client
    {
        return new Client(
            apiKey: 'k',
            transport: $transport,
            autoReferenceIds: true,
            logger: $logger,
            sleeper: static function (float $s): void {},
            random: static fn(): float => 0.0,
        );
    }

    /**
     * @return array<mixed>
     */
    private function body(Request $request): array
    {
        $body = json_decode((string) $request->body, true);
        self::assertIsArray($body);

        return $body;
    }
}
