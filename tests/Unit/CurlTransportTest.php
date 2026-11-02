<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unicom360\Client;
use Unicom360\Exception\ApiConnectionException;
use Unicom360\Http\CurlTransport;
use Unicom360\Http\Request;
use Unicom360\Http\TransportException;

/**
 * CurlTransport against PHP's built-in web server on 127.0.0.1.
 */
final class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;

    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        self::$port = self::freePort();
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, __DIR__ . '/../Support/server-router.php'];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!\is_resource($process)) {
            self::fail('Could not start the PHP built-in server');
        }
        self::$server = $process;
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.2);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(50_000);
        }
        self::fail('The PHP built-in server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (\is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    public function testPostSendsMethodHeadersAndBody(): void
    {
        $response = (new CurlTransport())->send(new Request(
            'POST',
            $this->url('/api/v1/json/bulk/sms'),
            ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'X-API-Key' => 'k', 'User-Agent' => 'unicom360-php/test'],
            '{"message":"' . str_repeat('x', 2000) . '"}',
            5.0,
            2.0,
        ));

        self::assertSame(200, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->header('Content-Type'));
        self::assertSame('one, two', $response->headers['x-repeated'] ?? null);
        $echo = json_decode($response->body, true);
        self::assertIsArray($echo);
        self::assertSame('POST', $echo['method']);
        self::assertSame('/api/v1/json/bulk/sms', $echo['path']);
        self::assertIsArray($echo['headers']);
        self::assertSame('k', $echo['headers']['x-api-key'] ?? null);
        self::assertSame('unicom360-php/test', $echo['headers']['user-agent'] ?? null);
        self::assertArrayNotHasKey('expect', $echo['headers'], 'no Expect: 100-continue');
        self::assertArrayNotHasKey('authorization', $echo['headers']);
        self::assertSame('{"message":"' . str_repeat('x', 2000) . '"}', $echo['body']);
    }

    public function testGetSendsNoBody(): void
    {
        $response = (new CurlTransport())->send(new Request('GET', $this->url('/health'), ['Accept' => 'application/json'], null, 5.0, 2.0));
        $echo = json_decode($response->body, true);
        self::assertIsArray($echo);
        self::assertSame('GET', $echo['method']);
        self::assertSame('', $echo['body']);
    }

    public function testEveryStatusIsReturnedAndRedirectsAreNotFollowed(): void
    {
        $transport = new CurlTransport();
        self::assertSame(418, $transport->send(new Request('GET', $this->url('/teapot'), [], null, 5.0, 2.0))->status);

        $redirect = $transport->send(new Request('POST', $this->url('/redirect'), [], '{}', 5.0, 2.0));
        self::assertSame(302, $redirect->status);
        self::assertSame('https://elsewhere.example/', $redirect->header('location'));
        self::assertSame('{"moved":true}', $redirect->body);
    }

    public function testCompressedResponsesAreDecoded(): void
    {
        $response = (new CurlTransport())->send(new Request('GET', $this->url('/gzip'), [], null, 5.0, 2.0));
        self::assertSame('{"compressed":true}', $response->body);
    }

    public function testRefusedConnectionIsNotSent(): void
    {
        $port = self::freePort();
        try {
            (new CurlTransport())->send(new Request('POST', "http://127.0.0.1:{$port}/x", [], '{}', 5.0, 2.0));
            self::fail('expected a TransportException');
        } catch (TransportException $e) {
            self::assertFalse($e->sent);
            self::assertFalse($e->timeout);
            self::assertStringContainsString('cURL error 7', $e->getMessage());
        }
    }

    public function testResponseTimeoutAfterSendingIsSent(): void
    {
        try {
            (new CurlTransport())->send(new Request('POST', $this->url('/slow'), [], '{}', 0.3, 2.0));
            self::fail('expected a TransportException');
        } catch (TransportException $e) {
            self::assertTrue($e->sent);
            self::assertTrue($e->timeout);
        }
    }

    public function testTheHandleIsReusedAfterAFailure(): void
    {
        $transport = new CurlTransport();
        try {
            $transport->send(new Request('POST', $this->url('/slow'), [], '{}', 0.3, 2.0));
        } catch (TransportException) {
        }
        self::assertSame(200, $transport->send(new Request('GET', $this->url('/health'), [], null, 5.0, 2.0))->status);
    }

    public function testClientEndToEndThroughCurl(): void
    {
        $client = new Client(apiKey: 'k', baseUrl: 'http://127.0.0.1:' . self::$port);
        $health = $client->health();
        // The router echoes the request, so the "status" field is absent and raw holds the echo.
        self::assertSame('GET', $health->raw['method'] ?? null);
        self::assertSame('/health', $health->raw['path'] ?? null);

        $refused = new Client(apiKey: 'k', baseUrl: 'http://127.0.0.1:' . self::freePort(), maxRetries: 1, sleeper: static function (float $s): void {}, random: static fn(): float => 0.0);
        try {
            $refused->sms->send(from: 'A', to: '1', message: 'm');
            self::fail('expected a connection error');
        } catch (ApiConnectionException $e) {
            // A refused connection is retried even for a send without a reference.
            self::assertSame(2, $e->getAttempts());
            self::assertInstanceOf(TransportException::class, $e->getPrevious());
        }
    }

    private function url(string $path): string
    {
        return 'http://127.0.0.1:' . self::$port . $path;
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::fail("No free port: {$errstr}");
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
