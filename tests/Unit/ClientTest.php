<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\AppInfo;
use Unicom360\Client;
use Unicom360\Exception\ApiStatusException;
use Unicom360\Exception\ConfigurationException;
use Unicom360\Http\Response;
use Unicom360\Tests\Support\FakeTransport;

final class ClientTest extends TestCase
{
    private const OK_HEALTH = '{"status":"ok","timestamp":"2026-10-22T08:15:30.412Z","version":"0.1.0","environment":"production"}';
    private const OK_BALANCE = '{"status":true,"message":"Balance retrieved successfully","balance":1250,"bundle_type":"SMS","client_id":"UC-00042","client_name":"Acme Ltd"}';

    /** @var array<string, string|false> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        foreach (['UNICOM360_API_KEY', 'UNICOM360_BASE_URL'] as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testMissingApiKeyFailsAtConstruction(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('UNICOM360_API_KEY');
        new Client();
    }

    public function testApiKeyFromTheEnvironment(): void
    {
        putenv('UNICOM360_API_KEY=env-key');
        $transport = new FakeTransport([new Response(200, [], self::OK_BALANCE)]);
        (new Client(transport: $transport))->balance->get();
        self::assertSame('env-key', $transport->lastRequest()->headers['X-API-Key']);
    }

    public function testApiKeyFromEnvSuperglobal(): void
    {
        $_ENV['UNICOM360_API_KEY'] = 'dotenv-key';
        $transport = new FakeTransport([new Response(200, [], self::OK_BALANCE)]);
        (new Client(transport: $transport))->balance->get();
        self::assertSame('dotenv-key', $transport->lastRequest()->headers['X-API-Key']);
        unset($_ENV['UNICOM360_API_KEY']);
    }

    public function testExplicitArgumentWinsOverTheEnvironment(): void
    {
        putenv('UNICOM360_API_KEY=env-key');
        $transport = new FakeTransport([new Response(200, [], self::OK_BALANCE)]);
        (new Client(apiKey: 'arg-key', transport: $transport))->balance->get();
        self::assertSame('arg-key', $transport->lastRequest()->headers['X-API-Key']);
    }

    public function testExplicitEmptyKeyDoesNotFallBackToTheEnvironment(): void
    {
        putenv('UNICOM360_API_KEY=env-key');
        $this->expectException(ConfigurationException::class);
        new Client(apiKey: '');
    }

    public function testWhitespaceOnlyKeyIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);
        new Client(apiKey: " \t ");
    }

    public function testKeyWithControlCharactersIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);
        new Client(apiKey: "key\r\nX-Injected: 1");
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedBaseUrls(): array
    {
        return [
            'default' => ['https://api.unicom360.com', 'https://api.unicom360.com'],
            'trailing slashes removed' => ['https://api.unicom360.com///', 'https://api.unicom360.com'],
            'path prefix kept' => ['https://proxy.example.com/unicom360/', 'https://proxy.example.com/unicom360'],
            'port' => ['https://proxy.example.com:8443', 'https://proxy.example.com:8443'],
            'http localhost' => ['http://localhost:4010', 'http://localhost:4010'],
            'http 127.0.0.1' => ['http://127.0.0.1:4012', 'http://127.0.0.1:4012'],
            'http 127.x' => ['http://127.10.20.30', 'http://127.10.20.30'],
            'http ::1' => ['http://[::1]:8080', 'http://[::1]:8080'],
            'upper-case scheme' => ['HTTPS://api.unicom360.com', 'HTTPS://api.unicom360.com'],
        ];
    }

    #[DataProvider('acceptedBaseUrls')]
    public function testAcceptedBaseUrl(string $given, string $expected): void
    {
        self::assertSame($expected, (new Client(apiKey: 'k', baseUrl: $given))->baseUrl());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function refusedBaseUrls(): array
    {
        return [
            'plain http to another host' => ['http://api.unicom360.com'],
            'http to a private address' => ['http://10.0.0.1'],
            'http to a lookalike host' => ['http://localhost.example.com'],
            'another scheme' => ['ftp://api.unicom360.com'],
            'no scheme' => ['api.unicom360.com'],
            'no host' => ['https://'],
            'query string' => ['https://api.unicom360.com/?a=1'],
            'fragment' => ['https://api.unicom360.com/#x'],
            'credentials' => ['https://user:pass@api.unicom360.com'],
            'whitespace' => ['https://api.unicom360.com /x'],
            'empty' => [''],
        ];
    }

    #[DataProvider('refusedBaseUrls')]
    public function testRefusedBaseUrl(string $given): void
    {
        $this->expectException(ConfigurationException::class);
        new Client(apiKey: 'k', baseUrl: $given);
    }

    public function testBaseUrlFromTheEnvironment(): void
    {
        putenv('UNICOM360_BASE_URL=http://127.0.0.1:4012/');
        self::assertSame('http://127.0.0.1:4012', (new Client(apiKey: 'k'))->baseUrl());
    }

    public function testPathPrefixIsKeptInRequests(): void
    {
        $transport = new FakeTransport([new Response(200, [], self::OK_HEALTH)]);
        (new Client(apiKey: 'k', baseUrl: 'https://proxy.example.com/unicom360/', transport: $transport))->health();
        self::assertSame('https://proxy.example.com/unicom360/health', $transport->lastRequest()->url);
    }

    /**
     * @return array<string, array{0: \Closure(): Client}>
     */
    public static function invalidOptions(): array
    {
        return [
            'negative maxRetries' => [static fn(): Client => new Client('k', maxRetries: -1)],
            'zero timeout' => [static fn(): Client => new Client('k', timeout: 0.0)],
            'negative connectTimeout' => [static fn(): Client => new Client('k', connectTimeout: -1.0)],
            'negative maxRetryWait' => [static fn(): Client => new Client('k', maxRetryWait: -0.5)],
            'infinite timeout' => [static fn(): Client => new Client('k', timeout: INF)],
            'logger without methods' => [static fn(): Client => new Client('k', logger: new \stdClass())],
            'appInfo without a name' => [static fn(): Client => new Client('k', appInfo: new AppInfo(' '))],
        ];
    }

    /**
     * @param \Closure(): Client $build
     */
    #[DataProvider('invalidOptions')]
    public function testInvalidOptions(\Closure $build): void
    {
        $this->expectException(ConfigurationException::class);
        $build();
    }

    public function testUserAgent(): void
    {
        $client = new Client(apiKey: 'k');
        self::assertMatchesRegularExpression('#^unicom360-php/0\.1\.0 \(php \d+\.\d+\.\d+[^;]*; [a-z]+; [^)]+\)$#', $client->userAgent());

        $withApp = new Client(apiKey: 'k', appInfo: new AppInfo('shopsync', '2.3.0', 'https://shopsync.example'));
        self::assertStringEndsWith(') shopsync/2.3.0 (+https://shopsync.example)', $withApp->userAgent());

        $fromArray = new Client(apiKey: 'k', appInfo: ['name' => 'crm']);
        self::assertStringEndsWith(') crm', $fromArray->userAgent());
    }

    public function testTheApiKeyIsRedactedEverywhere(): void
    {
        $key = 'sk_live_very_secret_value_123';
        $client = new Client(apiKey: $key);

        self::assertStringNotContainsString($key, print_r($client, true));
        self::assertStringNotContainsString($key, var_export($client, true));
        self::assertStringNotContainsString($key, (string) json_encode($client));
        self::assertStringNotContainsString($key, print_r($client->sms, true));
        ob_start();
        var_dump($client, $client->whatsapp);
        $dump = (string) ob_get_clean();
        self::assertStringNotContainsString($key, $dump);
        self::assertStringContainsString('[REDACTED]', $dump);

        $this->expectException(\LogicException::class);
        serialize($client);
    }

    public function testErrorsNeverContainTheApiKey(): void
    {
        $key = 'sk_live_very_secret_value_123';
        $transport = new FakeTransport([new \RuntimeException('boom')]);
        $client = new Client(apiKey: $key, transport: $transport, maxRetries: 0);
        try {
            $client->balance->get();
            self::fail('expected an error');
        } catch (\Throwable $e) {
            self::assertStringNotContainsString($key, $e->getMessage());
            self::assertStringNotContainsString($key, $e->getTraceAsString());
        }
    }

    public function testWithOptionsReturnsAChangedCopy(): void
    {
        $transport = new FakeTransport([], new Response(503, [], ''));
        $client = new Client(apiKey: 'k', transport: $transport, maxRetries: 0, sleeper: static function (float $s): void {}, random: static fn(): float => 0.0);
        $patient = $client->withOptions(maxRetries: 3);

        self::assertNotSame($client, $patient);
        try {
            $client->balance->get();
        } catch (ApiStatusException $e) {
            self::assertSame(1, $e->getAttempts());
        }
        try {
            $patient->balance->get();
        } catch (ApiStatusException $e) {
            self::assertSame(4, $e->getAttempts());
        }
        self::assertSame('k', $transport->lastRequest()->headers['X-API-Key']);
        self::assertSame($client->baseUrl(), $patient->baseUrl());
    }

    public function testPerCallTimeoutAndMaxRetries(): void
    {
        $transport = new FakeTransport([], new Response(500, [], ''));
        $client = new Client(apiKey: 'k', transport: $transport, timeout: 30.0, maxRetries: 0, sleeper: static function (float $s): void {}, random: static fn(): float => 0.0);
        try {
            $client->whatsapp->templates(timeout: 5.0, maxRetries: 2);
            self::fail('expected an error');
        } catch (ApiStatusException $e) {
            self::assertSame(3, $e->getAttempts());
        }
        self::assertSame(5.0, $transport->lastRequest()->timeout);
        self::assertSame(10.0, $transport->lastRequest()->connectTimeout);

        $this->expectException(ConfigurationException::class);
        $client->health(maxRetries: -1);
    }

    public function testRedirectsAreErrorsNotFollowed(): void
    {
        $transport = new FakeTransport([new Response(302, ['Location' => 'https://elsewhere.example/'], '')]);
        $client = new Client(apiKey: 'k', transport: $transport, maxRetries: 2);
        try {
            $client->balance->all();
            self::fail('expected an error');
        } catch (ApiStatusException $e) {
            self::assertSame(302, $e->getHttpStatus());
            self::assertSame('HTTP 302 Found', $e->getMessage());
            self::assertSame('https://elsewhere.example/', $e->getHeaders()['location'] ?? null);
            self::assertSame(1, $e->getAttempts());
        }
        self::assertCount(1, $transport->requests);
    }

    public function testVersionMatchesTheChangelog(): void
    {
        $changelog = file_get_contents(__DIR__ . '/../../CHANGELOG.md');
        self::assertIsString($changelog);
        if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $m) !== 1) {
            self::fail('CHANGELOG has no versioned section');
        }
        self::assertSame(Client::VERSION, $m[1], 'Client::VERSION is the newest CHANGELOG version');
    }

    public function testComposerJsonDeclaresNoVersion(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);
        self::assertIsArray($composer);
        self::assertArrayNotHasKey('version', $composer, 'Packagist reads versions from tags');
        self::assertSame('unicom360/unicom360-php', $composer['name'] ?? null);
    }
}
