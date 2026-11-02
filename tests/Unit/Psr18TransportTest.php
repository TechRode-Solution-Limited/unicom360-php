<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Unicom360\Client;
use Unicom360\Exception\ApiConnectionException;
use Unicom360\Exception\ApiTimeoutException;
use Unicom360\Http\Psr18Transport;
use Unicom360\Http\Request;
use Unicom360\Http\TransportException;
use Unicom360\Tests\Support\FakeNetworkException;
use Unicom360\Tests\Support\FakePsr18Client;

final class Psr18TransportTest extends TestCase
{
    public function testRequestAndResponseAreMapped(): void
    {
        $client = new FakePsr18Client(static fn(RequestInterface $r): ResponseInterface => new PsrResponse(
            201,
            ['Content-Type' => 'application/json', 'X-Multi' => ['a', 'b']],
            '{"ok":true}',
        ));
        $factory = new Psr17Factory();
        $response = (new Psr18Transport($client, $factory, $factory))->send(new Request(
            'POST',
            'https://api.unicom360.com/api/v1/json/bulk/sms',
            ['Accept' => 'application/json', 'X-API-Key' => 'k', 'Content-Type' => 'application/json'],
            '{"a":1}',
            60.0,
            10.0,
        ));

        self::assertSame(201, $response->status);
        self::assertSame('{"ok":true}', $response->body);
        self::assertSame('a, b', $response->header('x-multi'));
        self::assertSame('application/json', $response->header('content-type'));

        $sent = $client->requests[0];
        self::assertSame('POST', $sent->getMethod());
        self::assertSame('https://api.unicom360.com/api/v1/json/bulk/sms', (string) $sent->getUri());
        self::assertSame('k', $sent->getHeaderLine('X-API-Key'));
        self::assertSame('{"a":1}', (string) $sent->getBody());
    }

    public function testGetHasNoBody(): void
    {
        $client = new FakePsr18Client(static fn(RequestInterface $r): ResponseInterface => new PsrResponse(200, [], '{}'));
        $factory = new Psr17Factory();
        (new Psr18Transport($client, $factory, $factory))->send(new Request('GET', 'https://api.unicom360.com/health', [], null, 60.0, 10.0));
        self::assertSame('', (string) $client->requests[0]->getBody());
    }

    public function testFailuresAreTreatedAsPossiblySent(): void
    {
        $factory = new Psr17Factory();
        $network = new Psr18Transport(new FakePsr18Client(static function (RequestInterface $r): ResponseInterface {
            throw new FakeNetworkException('Connection refused', $r);
        }), $factory, $factory);
        try {
            $network->send(new Request('GET', 'https://api.unicom360.com/health', [], null, 60.0, 10.0));
            self::fail('expected a TransportException');
        } catch (TransportException $e) {
            self::assertTrue($e->sent);
            self::assertFalse($e->timeout);
            self::assertInstanceOf(NetworkExceptionInterface::class, $e->getPrevious());
        }

        $timeout = new Psr18Transport(new FakePsr18Client(static function (RequestInterface $r): ResponseInterface {
            throw new FakeNetworkException('cURL error 28: Operation timed out after 60000 milliseconds', $r);
        }), $factory, $factory);
        try {
            $timeout->send(new Request('GET', 'https://api.unicom360.com/health', [], null, 60.0, 10.0));
            self::fail('expected a TransportException');
        } catch (TransportException $e) {
            self::assertTrue($e->sent);
            self::assertTrue($e->timeout);
        }
    }

    public function testClientThroughPsr18(): void
    {
        $factory = new Psr17Factory();
        $calls = 0;
        $fake = new FakePsr18Client(static function (RequestInterface $r) use (&$calls): ResponseInterface {
            $calls++;
            if ($calls === 1) {
                throw new FakeNetworkException('timed out', $r);
            }

            return new PsrResponse(200, [], '{"status":true,"message":"ok","balance":5,"bundle_type":"SMS"}');
        });
        $client = new Client(
            apiKey: 'k',
            transport: new Psr18Transport($fake, $factory, $factory),
            sleeper: static function (float $s): void {},
            random: static fn(): float => 0.0,
        );

        // A read is retried after a failure that may have been sent.
        self::assertSame(5.0, $client->balance->get()->balance);
        self::assertSame(2, $calls);

        // A send without a reference is not.
        $calls = 0;
        try {
            $client->sms->send(from: 'A', to: '1', message: 'm');
            self::fail('expected a timeout');
        } catch (ApiConnectionException $e) {
            self::assertInstanceOf(ApiTimeoutException::class, $e);
            self::assertSame(1, $e->getAttempts());
        }
    }
}
