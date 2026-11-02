<?php

declare(strict_types=1);

namespace Unicom360\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Unicom360\Client;
use Unicom360\Exception\ApiException;
use Unicom360\Http\CurlTransport;
use Unicom360\Http\Request;
use Unicom360\Http\Response;
use Unicom360\Http\Transport;
use Unicom360\Tests\Support\Fx;
use Unicom360\Tests\Support\Harness;

/**
 * Every requests.json call, sent with the real CurlTransport to a Prism mock of openapi.yaml.
 * Prism validates each request against the spec: any violation fails the test.
 *
 *   docker run --rm -p 4012:4010 -v <unicom360-openapi>:/spec:ro stoplight/prism:5 \
 *       mock -h 0.0.0.0 --multiprocess=false --errors /spec/openapi.yaml
 *   vendor/bin/phpunit --group contract
 *
 * With --errors Prism also fails a response that breaks the spec. UNICOM360_CONTRACT_BASE_URL
 * overrides the default base URL, http://127.0.0.1:4012.
 */
#[Group('contract')]
final class PrismContractTest extends TestCase
{
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
    public function testPrismAcceptsTheRequest(array $vector): void
    {
        $id = Fx::str($vector, 'id');
        $operation = Fx::str($vector, 'operation');
        $transport = new class implements Transport {
            /** @var list<Response> */
            public array $responses = [];

            private CurlTransport $curl;

            public function __construct()
            {
                $this->curl = new CurlTransport();
            }

            public function send(Request $request): Response
            {
                return $this->responses[] = $this->curl->send($request);
            }
        };
        $client = new Client(apiKey: 'prism-test-key', baseUrl: self::baseUrl(), transport: $transport, maxRetries: 0, timeout: 15.0);

        try {
            $result = Harness::call($client, $operation, Fx::arr($vector, 'params'));
            self::assertInstanceOf(Harness::resultClass($operation), $result);
        } catch (ApiException $e) {
            // Prism may answer with a documented success: false example; that is a valid answer.
            self::assertSame(200, $e->getHttpStatus(), "{$id}: refusal on HTTP 200");
        }

        self::assertCount(1, $transport->responses);
        $response = $transport->responses[0];
        self::assertNull($response->header('sl-violations'), "{$id}: Prism reported violations: " . ($response->header('sl-violations') ?? ''));
        self::assertNotSame(422, $response->status, "{$id}: Prism rejected the request: {$response->body}");
        self::assertGreaterThanOrEqual(200, $response->status, "{$id}: status");
        self::assertLessThan(300, $response->status, "{$id}: status {$response->status}: {$response->body}");
    }

    private static function baseUrl(): string
    {
        $url = getenv('UNICOM360_CONTRACT_BASE_URL');

        return \is_string($url) && $url !== '' ? $url : 'http://127.0.0.1:4012';
    }
}
