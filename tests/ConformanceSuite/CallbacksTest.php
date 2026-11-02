<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Tests\Support\Canonical;
use Unicom360\Tests\Support\Fx;
use Unicom360\Webhook;

/**
 * conformance/callbacks.json: the unsigned SMS and email delivery reports.
 */
final class CallbacksTest extends TestCase
{
    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('callbacks.json');
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testCallback(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'kind', 'body_text', 'expect', 'off_spec'], 'callbacks.json vector');
        $id = Fx::str($vector, 'id');
        $kind = Fx::str($vector, 'kind');
        $body = Fx::str($vector, 'body_text');
        $expect = Fx::arr($vector, 'expect');
        Fx::assertKnownKeys($expect, ['result', 'error'], "{$id} expect");
        $parse = match ($kind) {
            'sms' => Webhook::parseSmsCallback(...),
            'email' => Webhook::parseEmailCallback(...),
            default => throw new \LogicException("{$id}: unknown callback kind {$kind}"),
        };

        if (isset($expect['error'])) {
            self::assertTrue(Fx::bool($expect, 'error'), "{$id}: expect.error is true when present");
            $this->expectException(Unicom360Exception::class);
            $parse($body);

            return;
        }

        $report = $parse($body);
        $canonical = Canonical::result($report);
        self::assertTrue(
            Canonical::equal(Fx::arr($expect, 'result'), $canonical),
            "{$id}: report differs: " . json_encode($canonical),
        );
        self::assertIsString($report->messageId, "{$id}: message_id is a string");
    }
}
