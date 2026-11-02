<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Model\EmailSendResult;
use Unicom360\Model\SmsSendResult;
use Unicom360\Model\TemplateSendResult;
use Unicom360\Tests\Support\Canonical;
use Unicom360\Tests\Support\FakeTransport;
use Unicom360\Tests\Support\Fx;
use Unicom360\Tests\Support\Harness;

/**
 * conformance/responses.json: canned responses and the result or error each must produce.
 */
final class ResponsesTest extends TestCase
{
    use ErrorAssertions;

    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('responses.json');
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testResponse(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'operation', 'now_unix', 'http', 'expect', 'off_spec'], 'responses.json vector');
        $id = Fx::str($vector, 'id');
        $operation = Fx::str($vector, 'operation');
        $now = Fx::optNum($vector, 'now_unix') ?? 1767225630.0;
        $expect = Fx::arr($vector, 'expect');
        Fx::assertKnownKeys($expect, ['result', 'helpers', 'error'], "{$id} expect");

        $transport = FakeTransport::always(Fx::arr($vector, 'http'));
        $client = Harness::client($transport, clock: static fn(): float => $now);

        try {
            $result = Harness::call($client, $operation, Harness::sampleParams($operation));
        } catch (Unicom360Exception $error) {
            self::assertArrayHasKey('error', $expect, "{$id}: unexpected error " . $error::class . ': ' . $error->getMessage());
            self::assertErrorMatches(Fx::arr($expect, 'error'), $error, $id);
            self::assertSame(1, $error->getAttempts(), "{$id}: attempts");
            self::assertCount(1, $transport->requests);

            return;
        }

        self::assertArrayNotHasKey('error', $expect, "{$id}: expected an error, got a result");
        self::assertArrayHasKey('result', $expect, "{$id}: expect has neither result nor error");
        self::assertInstanceOf(Harness::resultClass($operation), $result);
        $canonical = Canonical::result($result);
        self::assertTrue(
            Canonical::equal($expect['result'], $canonical),
            "{$id}: canonical result differs\nexpected: " . json_encode($expect['result']) . "\nactual:   " . json_encode($canonical),
        );

        if (isset($expect['helpers'])) {
            $this->assertHelpers($id, $result, Fx::arr($expect, 'helpers'));
        }
    }

    /**
     * @param array<mixed> $helpers
     */
    private function assertHelpers(string $id, object $result, array $helpers): void
    {
        self::assertTrue(
            $result instanceof SmsSendResult || $result instanceof EmailSendResult || $result instanceof TemplateSendResult,
            "{$id}: helpers are for send results only",
        );
        $results = $result->results;
        $position = static function (mixed $item) use ($results): ?int {
            if (!\is_object($item)) {
                return null;
            }
            $index = array_search($item, $results, true);

            return \is_int($index) ? $index : null;
        };
        $positions = static fn(array $items): array => array_map($position, $items);

        foreach ($helpers as $name => $expected) {
            switch ($name) {
                case 'accepted':
                    self::assertSame(Fx::list($helpers, 'accepted'), $positions($result->accepted()), "{$id}: accepted()");
                    break;
                case 'invalid':
                    self::assertFalse($result instanceof TemplateSendResult, "{$id}: invalid() is for SMS and email");
                    self::assertSame(Fx::list($helpers, 'invalid'), $positions($result->invalid()), "{$id}: invalid()");
                    break;
                case 'rejected':
                    self::assertInstanceOf(TemplateSendResult::class, $result, "{$id}: rejected() is for templates");
                    self::assertSame(Fx::list($helpers, 'rejected'), $positions($result->rejected()), "{$id}: rejected()");
                    break;
                case 'by_number':
                    self::assertFalse($result instanceof EmailSendResult, "{$id}: byNumber() is for SMS and templates");
                    foreach (Fx::arr($helpers, 'by_number') as $number => $wanted) {
                        self::assertSame($wanted, $position($result->byNumber((string) $number)), "{$id}: byNumber({$number})");
                    }
                    break;
                case 'by_address':
                    self::assertInstanceOf(EmailSendResult::class, $result, "{$id}: byAddress() is for email");
                    foreach (Fx::arr($helpers, 'by_address') as $address => $wanted) {
                        self::assertSame($wanted, $position($result->byAddress((string) $address)), "{$id}: byAddress({$address})");
                    }
                    break;
                default:
                    self::fail("{$id}: unknown helper {$name}");
            }
        }
    }
}
