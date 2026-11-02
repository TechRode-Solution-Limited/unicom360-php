<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Tests\Support\FakeTransport;
use Unicom360\Tests\Support\Fx;
use Unicom360\Tests\Support\Harness;

/**
 * conformance/retries.json: response and transport sequences, and the attempts, sleeps and
 * outcome each must produce. Every vector runs with three random values (the jitter seam).
 */
final class RetriesTest extends TestCase
{
    use ErrorAssertions;

    private const RANDOM_VALUES = [0.0, 0.5, 0.999999];

    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('retries.json');
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testRetries(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'operation', 'params', 'now_unix', 'config', 'steps', 'expect'], 'retries.json vector');
        foreach (self::RANDOM_VALUES as $random) {
            $this->play($vector, $random);
        }
    }

    /**
     * @param array<mixed> $vector
     */
    private function play(array $vector, float $random): void
    {
        $id = Fx::str($vector, 'id') . " (random {$random})";
        $operation = Fx::str($vector, 'operation');
        $config = Fx::arr($vector, 'config');
        Fx::assertKnownKeys($config, ['max_retries', 'max_retry_wait'], "{$id} config");
        $expect = Fx::arr($vector, 'expect');
        Fx::assertKnownKeys($expect, ['attempts', 'sleeps', 'outcome'], "{$id} expect");

        $steps = [];
        foreach (Fx::list($vector, 'steps') as $step) {
            $step = Fx::asArray($step, 'step');
            Fx::assertKnownKeys($step, ['http', 'transport'], "{$id} step");
            if (!isset($step['http'])) {
                // Fails on an unknown kind instead of skipping it.
                FakeTransport::failure(Fx::str($step, 'transport'));
            }
            $steps[] = $step;
        }

        // The fake clock moves only when the fake sleeper sleeps.
        $clock = Fx::num($vector, 'now_unix');
        $sleeps = [];
        $transport = new FakeTransport($steps);
        $client = Harness::client(
            $transport,
            maxRetries: Fx::int($config, 'max_retries'),
            maxRetryWait: Fx::num($config, 'max_retry_wait'),
            clock: static function () use (&$clock): float {
                return $clock;
            },
            sleeper: static function (float $seconds) use (&$clock, &$sleeps): void {
                $sleeps[] = $seconds;
                $clock += $seconds;
            },
            random: static fn(): float => $random,
        );

        $error = null;
        $result = null;
        try {
            $result = Harness::call($client, $operation, Fx::arr($vector, 'params'));
        } catch (Unicom360Exception $e) {
            $error = $e;
        }

        $attempts = Fx::int($expect, 'attempts');
        self::assertCount($attempts, $transport->requests, "{$id}: attempts");

        $expectedSleeps = Fx::list($expect, 'sleeps');
        self::assertCount(\count($expectedSleeps), $sleeps, "{$id}: number of sleeps: " . json_encode($sleeps));
        foreach ($expectedSleeps as $i => $range) {
            $range = Fx::asArray($range, 'sleep');
            Fx::assertKnownKeys($range, ['min', 'max'], "{$id} sleep");
            self::assertGreaterThanOrEqual(Fx::num($range, 'min') - 1e-6, $sleeps[$i], "{$id}: sleep {$i}");
            self::assertLessThanOrEqual(Fx::num($range, 'max') + 1e-6, $sleeps[$i], "{$id}: sleep {$i}");
        }

        $outcome = Fx::arr($expect, 'outcome');
        $kind = Fx::str($outcome, 'kind');
        if ($kind === 'result') {
            self::assertNull($error, "{$id}: expected a result, got " . ($error === null ? '' : $error::class . ': ' . $error->getMessage()));
            self::assertInstanceOf(Harness::resultClass($operation), $result);

            return;
        }
        self::assertSame('error', $kind, "{$id}: outcome kind");
        self::assertNotNull($error, "{$id}: expected an error, got a result");
        self::assertErrorMatches($outcome, $error, $id);
        self::assertSame($attempts, $error->getAttempts(), "{$id}: the error's attempts");
    }
}
