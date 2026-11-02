<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Assert;
use Unicom360\Exception\ApiException;
use Unicom360\Exception\ApiStatusException;
use Unicom360\Exception\RateLimitException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Tests\Support\Canonical;
use Unicom360\Tests\Support\Fx;

/**
 * The error checks responses.json and retries.json share.
 */
trait ErrorAssertions
{
    /**
     * @param array<mixed> $expected
     */
    private static function assertErrorMatches(array $expected, Unicom360Exception $error, string $id): void
    {
        Fx::assertKnownKeys($expected, ['kind', 'type', 'class', 'code', 'message', 'api_reference', 'http_status', 'data_present', 'retry_after'], "{$id} error");

        Assert::assertSame(Fx::str($expected, 'type'), $error->getType(), "{$id}: type");
        Assert::assertSame(Fx::str($expected, 'class'), Canonical::className($error), "{$id}: class");
        if (\array_key_exists('code', $expected)) {
            Assert::assertSame(Fx::optStr($expected, 'code'), Canonical::errorCode($error), "{$id}: code");
        }
        if (\array_key_exists('http_status', $expected)) {
            $status = $expected['http_status'];
            Assert::assertSame(\is_int($status) ? $status : null, $error->getHttpStatus(), "{$id}: http_status");
        }
        if (\array_key_exists('message', $expected)) {
            $message = Fx::optStr($expected, 'message');
            if ($message === null) {
                Assert::assertNotSame('', $error->getMessage(), "{$id}: a generated message");
            } else {
                Assert::assertSame($message, $error->getMessage(), "{$id}: message");
            }
        }
        if (\array_key_exists('api_reference', $expected)) {
            Assert::assertSame(Fx::optStr($expected, 'api_reference'), $error->getApiReference(), "{$id}: api_reference");
        }
        if (\array_key_exists('data_present', $expected)) {
            $data = $error instanceof ApiStatusException || $error instanceof ApiException ? $error->getData() : null;
            Assert::assertSame(Fx::bool($expected, 'data_present'), $data !== null, "{$id}: data_present");
        }
        if (\array_key_exists('retry_after', $expected)) {
            Assert::assertInstanceOf(RateLimitException::class, $error, "{$id}: retry_after needs a RateLimitException");
            Assert::assertEqualsWithDelta(Fx::num($expected, 'retry_after'), $error->getRetryAfter(), 1e-9, "{$id}: retry_after");
        }
    }
}
