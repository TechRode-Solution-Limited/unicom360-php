<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unicom360\Client;
use Unicom360\Http\Response;
use Unicom360\Http\TransportException;
use Unicom360\Tests\Support\FakeTransport;
use Unicom360\Tests\Support\RecordingLogger;

final class LoggingTest extends TestCase
{
    public function testOneDebugLinePerAttemptAndOneInfoLinePerRetry(): void
    {
        $logger = new RecordingLogger();
        $transport = new FakeTransport([
            new Response(429, [], '{"message":"RATE_LIMIT_EXCEEDED","success":false,"api_reference":"ref-429"}'),
            new TransportException('connection refused', false, false),
            new Response(200, [], '{"api_reference":"ref-ok","data":[{"message_id":"m","status":"ACKNOWLEDGED","mobile_number":"260971234567"}],"message":"successful","success":true}'),
        ]);
        $client = new Client(
            apiKey: 'sk_live_secret',
            transport: $transport,
            logger: $logger,
            clock: static fn(): float => 1767225630.0,
            sleeper: static function (float $s): void {},
            random: static fn(): float => 0.25,
        );
        $client->sms->send(from: 'Unicom360', to: '260971234567', message: 'Your code is 482193');

        $levels = array_column($logger->records, 'level');
        self::assertSame(['debug', 'info', 'debug', 'info', 'debug'], $levels);

        $first = $logger->records[0]['context'];
        self::assertSame(['method', 'path', 'attempt', 'status', 'api_reference', 'duration_ms'], array_keys($first));
        self::assertSame('POST', $first['method']);
        self::assertSame('/api/v1/json/bulk/sms', $first['path']);
        self::assertSame(1, $first['attempt']);
        self::assertSame(429, $first['status']);
        self::assertSame('ref-429', $first['api_reference']);

        $retry = $logger->records[1]['context'];
        self::assertSame(['method', 'path', 'attempt', 'wait_s', 'cause'], array_keys($retry));
        self::assertSame('HTTP 429', $retry['cause']);
        self::assertSame(30.25, $retry['wait_s']);

        $failed = $logger->records[2]['context'];
        self::assertSame('connection', $failed['error'] ?? null);
        self::assertFalse($failed['sent'] ?? null);
        self::assertSame(2, $failed['attempt']);

        self::assertSame('ref-ok', $logger->records[4]['context']['api_reference']);

        // Never the key, a body, a number or the message text.
        $everything = (string) json_encode($logger->records);
        foreach (['sk_live_secret', '260971234567', '482193', 'Unicom360"', 'message_id'] as $secret) {
            self::assertStringNotContainsString($secret, $everything);
        }
    }

    public function testNothingIsLoggedWithoutALogger(): void
    {
        $transport = new FakeTransport([new Response(200, [], '{"status":"ok"}')]);
        $this->expectOutputString('');
        (new Client(apiKey: 'k', transport: $transport))->health();
    }

    public function testAnyObjectWithDebugAndInfoWorks(): void
    {
        $logger = new class {
            /** @var list<string> */
            public array $lines = [];

            /**
             * @param array<mixed> $context
             */
            public function debug(string $message, array $context = []): void
            {
                $this->lines[] = $message;
            }

            /**
             * @param array<mixed> $context
             */
            public function info(string $message, array $context = []): void
            {
                $this->lines[] = $message;
            }
        };
        $transport = new FakeTransport([new Response(200, [], '{"status":"ok"}')]);
        (new Client(apiKey: 'k', transport: $transport, logger: $logger))->health();
        self::assertSame(['Unicom360 request'], $logger->lines);
    }
}
