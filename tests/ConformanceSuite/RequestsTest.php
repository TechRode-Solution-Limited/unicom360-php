<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Http\Request;
use Unicom360\Tests\Support\FakeTransport;
use Unicom360\Tests\Support\Fx;
use Unicom360\Tests\Support\Harness;
use Unicom360\Tests\Support\JsonValue;

/**
 * conformance/requests.json: each SDK call and the exact HTTP request it must make.
 */
final class RequestsTest extends TestCase
{
    private const ARRAY_FORM = ['sms.send', 'email.send', 'whatsapp.send_template', 'whatsapp.send_session'];

    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('requests.json');
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testRequest(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'operation', 'params', 'expect'], 'requests.json vector');
        $operation = Fx::str($vector, 'operation');
        $params = Fx::arr($vector, 'params');

        $transport = FakeTransport::always(self::canned($operation));
        $result = Harness::call(Harness::client($transport), $operation, $params);
        self::assertInstanceOf(Harness::resultClass($operation), $result);
        self::assertCount(1, $transport->requests);
        $this->assertRequestMatches($vector, $transport->requests[0]);

        if (\in_array($operation, self::ARRAY_FORM, true)) {
            // The dynamic form, one array keyed by the API's names, sends the same request.
            $arrayTransport = FakeTransport::always(self::canned($operation));
            Harness::callWithArray(Harness::client($arrayTransport), $operation, $params);
            self::assertCount(1, $arrayTransport->requests);
            $this->assertRequestMatches($vector, $arrayTransport->requests[0]);
        }
    }

    public function testEveryOperationHasACannedResponseAndAVector(): void
    {
        $file = Fx::file('requests.json');
        Fx::assertKnownKeys($file, ['version', 'description', 'canned_responses', 'vectors'], 'requests.json');
        $canned = Fx::arr($file, 'canned_responses');
        $covered = [];
        foreach (self::vectors() as [$vector]) {
            $covered[Fx::str($vector, 'operation')] = true;
        }
        foreach (Harness::OPERATIONS as $operation) {
            self::assertArrayHasKey($operation, $canned, "canned response for {$operation}");
            self::assertArrayHasKey($operation, $covered, "a vector for {$operation}");
        }
        foreach (array_keys($canned) as $operation) {
            self::assertContains($operation, Harness::OPERATIONS, "unknown operation {$operation} in canned_responses");
        }
    }

    /**
     * @param array<mixed> $vector
     */
    private function assertRequestMatches(array $vector, Request $request): void
    {
        $id = Fx::str($vector, 'id');
        $expect = Fx::arr($vector, 'expect');
        Fx::assertKnownKeys($expect, ['method', 'path', 'headers', 'absent_headers', 'user_agent_prefix', 'json', 'absent_keys'], "{$id} expect");

        self::assertSame(Fx::str($expect, 'method'), $request->method, "{$id}: method");
        $path = parse_url($request->url, PHP_URL_PATH);
        self::assertSame(Fx::str($expect, 'path'), $path, "{$id}: path");
        self::assertNull(parse_url($request->url, PHP_URL_QUERY), "{$id}: no query string");

        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        foreach (Fx::arr($expect, 'headers') as $name => $value) {
            $wanted = str_replace('<KEY>', Harness::API_KEY, \is_string($value) ? $value : '');
            self::assertSame($wanted, $headers[strtolower((string) $name)] ?? null, "{$id}: header {$name}");
        }
        foreach (Fx::strings($expect, 'absent_headers') as $name) {
            self::assertArrayNotHasKey(strtolower($name), $headers, "{$id}: header {$name} must be absent");
        }
        $prefix = Fx::str($expect, 'user_agent_prefix');
        $agent = $headers['user-agent'] ?? '';
        self::assertTrue($prefix !== '' && str_starts_with($agent, $prefix), "{$id}: User-Agent {$agent} starts with {$prefix}");

        // Compare bodies with objects kept apart from lists ({} is not []).
        $expectObject = JsonValue::vector('requests.json', $id)->expect ?? null;
        $expectedJson = $expectObject instanceof \stdClass ? ($expectObject->json ?? null) : null;
        if ($expectedJson === null) {
            self::assertTrue($request->body === null || $request->body === '', "{$id}: no body");

            return;
        }
        self::assertNotNull($request->body, "{$id}: a body");
        $actual = JsonValue::decode($request->body);
        $difference = JsonValue::difference($expectedJson, $actual);
        self::assertNull($difference, "{$id}: body differs at {$difference}: {$request->body}");
        foreach (Fx::strings($expect, 'absent_keys') as $pointer) {
            self::assertFalse(JsonValue::pointerExists($actual, $pointer), "{$id}: {$pointer} must be absent");
        }
    }

    /**
     * @return array<mixed>
     */
    private static function canned(string $operation): array
    {
        return Fx::arr(Fx::arr(Fx::file('requests.json'), 'canned_responses'), $operation);
    }
}
