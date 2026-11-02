<?php

declare(strict_types=1);

namespace Unicom360\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unicom360\Client;
use Unicom360\Exception\ApiConnectionException;
use Unicom360\Exception\ApiException;
use Unicom360\Exception\ApiServerException;
use Unicom360\Exception\ApiStatusException;
use Unicom360\Exception\ApiTimeoutException;
use Unicom360\Exception\AuthenticationException;
use Unicom360\Exception\ConfigurationException;
use Unicom360\Exception\DuplicateReferenceException;
use Unicom360\Exception\InsufficientBundleException;
use Unicom360\Exception\InvalidRequestException;
use Unicom360\Exception\NoValidRecipientsException;
use Unicom360\Exception\RateLimitException;
use Unicom360\Exception\SessionWindowClosedException;
use Unicom360\Exception\SignatureVerificationException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Http\Response;
use Unicom360\Http\TransportException;
use Unicom360\Tests\Support\FakeTransport;
use Unicom360\WhatsApp\Session;

final class ExceptionsTest extends TestCase
{
    public function testHierarchy(): void
    {
        $parents = [
            ConfigurationException::class => Unicom360Exception::class,
            ApiConnectionException::class => Unicom360Exception::class,
            ApiTimeoutException::class => ApiConnectionException::class,
            ApiStatusException::class => Unicom360Exception::class,
            AuthenticationException::class => ApiStatusException::class,
            RateLimitException::class => ApiStatusException::class,
            InvalidRequestException::class => ApiStatusException::class,
            ApiServerException::class => ApiStatusException::class,
            ApiException::class => Unicom360Exception::class,
            InsufficientBundleException::class => ApiException::class,
            DuplicateReferenceException::class => ApiException::class,
            NoValidRecipientsException::class => ApiException::class,
            SessionWindowClosedException::class => ApiException::class,
            SignatureVerificationException::class => Unicom360Exception::class,
        ];
        foreach ($parents as $class => $parent) {
            self::assertSame($parent, get_parent_class($class), $class);
        }
        self::assertSame(\RuntimeException::class, get_parent_class(Unicom360Exception::class));
    }

    public function testDuplicateReferenceCarriesTheOriginalMessageId(): void
    {
        $body = '{"message":"DUPLICATE_MSG_REF","success":false,"api_reference":"r","data":{"message_id":"7c1f3a2e-5b64-4d2f-9a8e-0f1b2c3d4e5f"}}';
        $error = $this->sessionError($body);
        self::assertInstanceOf(DuplicateReferenceException::class, $error);
        self::assertSame('7c1f3a2e-5b64-4d2f-9a8e-0f1b2c3d4e5f', $error->getOriginalMessageId());

        $raced = $this->sessionError('{"message":"DUPLICATE_MSG_REF","success":false,"api_reference":"r","data":{"message_id":null}}');
        self::assertInstanceOf(DuplicateReferenceException::class, $raced);
        self::assertNull($raced->getOriginalMessageId());
    }

    public function testNoValidRecipientsCarriesTheResults(): void
    {
        $body = '{"message":"NO_VALID_RECIPIENTS","success":false,"api_reference":"r","data":{"accepted":0,"rejected":1,"results":[{"category":"UTILITY","message_id":null,"reason":"INVALID_NUMBER","status":"REJECTED","to":12,"user_reference_id":"inv-1"}]}}';
        $transport = new FakeTransport([new Response(200, [], $body)]);
        try {
            (new Client(apiKey: 'k', transport: $transport))->whatsapp->sendTemplate(template: 't', recipients: [['to' => '12']]);
            self::fail('expected an error');
        } catch (NoValidRecipientsException $e) {
            $results = $e->getResults();
            self::assertCount(1, $results);
            self::assertSame('12', $results[0]->to);
            self::assertSame('INVALID_NUMBER', $results[0]->reason);
            self::assertFalse($results[0]->isAccepted());
        }
    }

    public function testConnectionErrorsKeepTheCause(): void
    {
        $cause = new TransportException('cURL error 7: Failed to connect', false, false);
        $transport = new FakeTransport([$cause]);
        try {
            (new Client(apiKey: 'k', transport: $transport, maxRetries: 0))->health();
            self::fail('expected an error');
        } catch (ApiConnectionException $e) {
            self::assertNotInstanceOf(ApiTimeoutException::class, $e);
            self::assertSame($cause, $e->getPrevious());
            self::assertSame('connection_error', $e->getType());
            self::assertNull($e->getHttpStatus());
            self::assertStringContainsString('GET /health', $e->getMessage());
        }
    }

    private function sessionError(string $body): Unicom360Exception
    {
        $transport = new FakeTransport([new Response(200, [], $body)]);
        try {
            (new Client(apiKey: 'k', transport: $transport))->whatsapp->sendSession(Session::text('1', 'x', userReferenceId: 's-1'));
        } catch (Unicom360Exception $e) {
            return $e;
        }
        self::fail('expected an error');
    }
}
