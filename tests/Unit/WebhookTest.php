<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Unicom360\Exception\SignatureVerificationException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Webhook;
use Unicom360\Webhook\MessageStatusEvent;
use Unicom360\Webhook\TestEvent;

final class WebhookTest extends TestCase
{
    private const SECRET = 'krISEvfh1iUWHg5sdWQd0ZnOvE6D6mrCsuFLNEuhGfI';
    private const BODY = '{"created_at":"2026-10-24T09:30:00Z","data":{"message":"This is a test event from Unicom360."},"event":"webhook.test"}';

    public function testPsr7RequestHeaders(): void
    {
        $now = time();
        $request = new ServerRequest('POST', '/webhooks/unicom360', $this->signedHeaders(self::BODY, $now), self::BODY);
        $event = Webhook::constructEvent((string) $request->getBody(), $request, self::SECRET);
        self::assertInstanceOf(TestEvent::class, $event);
        self::assertSame('3001', $event->deliveryId);
        self::assertSame('This is a test event from Unicom360.', $event->data->message);
    }

    public function testServerSuperglobalStyleHeaders(): void
    {
        $now = 1792829762;
        $server = [];
        foreach ($this->signedHeaders(self::BODY, $now) as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $server['REQUEST_METHOD'] = 'POST';
        Webhook::verify(self::BODY, $server, self::SECRET, now: $now);
        self::assertSame('3001', Webhook::header($server, 'X-Unicom360-Delivery'));
    }

    public function testHeaderBagStyleObjectAndListValues(): void
    {
        $now = 1792829762;
        $lists = [];
        foreach ($this->signedHeaders(self::BODY, $now) as $name => $value) {
            $lists[strtolower($name)] = [$value, 'ignored second value'];
        }
        Webhook::verify(self::BODY, $lists, self::SECRET, now: $now);

        $bag = new class ($lists) {
            /**
             * @param array<string, list<string>> $headers
             */
            public function __construct(private readonly array $headers) {}

            public function get(string $key): ?string
            {
                return $this->headers[strtolower($key)][0] ?? null;
            }
        };
        Webhook::verify(self::BODY, $bag, self::SECRET, now: $now);
        self::assertSame('3001', Webhook::header($bag, 'x-unicom360-delivery'));
        self::assertSame('3001', Webhook::header(new \ArrayIterator($lists), 'X-Unicom360-Delivery'));
    }

    public function testDefaultClockIsTheSystemClock(): void
    {
        Webhook::verify(self::BODY, $this->signedHeaders(self::BODY, time()), self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        Webhook::verify(self::BODY, $this->signedHeaders(self::BODY, time() - 3600), self::SECRET);
    }

    public function testTimestampTooLargeForA64BitIntegerIsOutOfTolerance(): void
    {
        $timestamp = '99999999999999999999999';
        $headers = [
            'X-Unicom360-Timestamp' => $timestamp,
            'X-Unicom360-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . self::BODY, self::SECRET),
        ];
        try {
            Webhook::verify(self::BODY, $headers, self::SECRET, now: 1792829762);
            self::fail('expected an error');
        } catch (SignatureVerificationException $e) {
            self::assertSame(SignatureVerificationException::TIMESTAMP_OUT_OF_TOLERANCE, $e->getReason());
        }
    }

    public function testLeadingZerosAreANumber(): void
    {
        $timestamp = '0001792829762';
        $headers = [
            'X-Unicom360-Timestamp' => $timestamp,
            'X-Unicom360-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . self::BODY, self::SECRET),
        ];
        Webhook::verify(self::BODY, $headers, self::SECRET, now: 1792829762);
        $this->addToAssertionCount(1);
    }

    public function testAValidSignatureOverABodyThatIsNotAnEventIsABaseError(): void
    {
        $now = 1792829762;
        foreach (['not json', '[1,2]', '{"event":42}', '{"data":{}}'] as $body) {
            try {
                Webhook::constructEvent($body, $this->signedHeaders($body, $now), self::SECRET, now: $now);
                self::fail("expected an error for {$body}");
            } catch (Unicom360Exception $e) {
                self::assertNotInstanceOf(SignatureVerificationException::class, $e);
                self::assertSame(Unicom360Exception::class, $e::class);
            }
        }
    }

    public function testMissingDeliveryHeaderIsNull(): void
    {
        $now = 1792829762;
        $body = '{"event":"message.status","created_at":"2026-10-24T08:16:02Z","data":{"message_id":"m","status":"delivered","error":null}}';
        $headers = $this->signedHeaders($body, $now);
        unset($headers['X-Unicom360-Delivery']);
        $event = Webhook::constructEvent($body, $headers, self::SECRET, now: $now);
        self::assertInstanceOf(MessageStatusEvent::class, $event);
        self::assertNull($event->deliveryId);
        self::assertSame('delivered', $event->data->status);
        self::assertNull($event->data->error);
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $body, int $timestamp): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-Unicom360-Event' => 'webhook.test',
            'X-Unicom360-Delivery' => '3001',
            'X-Unicom360-Timestamp' => (string) $timestamp,
            'X-Unicom360-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, self::SECRET),
        ];
    }
}
