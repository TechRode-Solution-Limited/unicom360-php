<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Gsm;
use Unicom360\Tests\Support\Fx;

/**
 * conformance/gsm7.json: GSM-7 checks, lengths and billing units.
 */
final class Gsm7Test extends TestCase
{
    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('gsm7.json');
    }

    public function testConstantsMatchTheFixture(): void
    {
        $file = Fx::file('gsm7.json');
        Fx::assertKnownKeys($file, ['version', 'description', 'charset', 'max_length', 'unit_length', 'vectors'], 'gsm7.json');
        self::assertSame(Fx::str($file, 'charset'), Gsm::CHARSET);
        self::assertSame(Fx::int($file, 'max_length'), Gsm::MAX_LENGTH);
        self::assertSame(Fx::int($file, 'unit_length'), Gsm::UNIT_LENGTH);
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testHelpers(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'text', 'is_gsm7', 'length', 'units', 'non_gsm', 'validate'], 'gsm7.json vector');
        $id = Fx::str($vector, 'id');
        $text = Fx::str($vector, 'text');

        self::assertSame(Fx::bool($vector, 'is_gsm7'), Gsm::isGsm7($text), "{$id}: isGsm7");
        self::assertSame(Fx::int($vector, 'length'), Gsm::length($text), "{$id}: length");
        self::assertSame(Fx::int($vector, 'units'), Gsm::units($text), "{$id}: units");
        $nonGsm = [];
        foreach (Fx::list($vector, 'non_gsm') as $entry) {
            $entry = Fx::asArray($entry, 'non_gsm entry');
            Fx::assertKnownKeys($entry, ['char', 'index'], "{$id} non_gsm");
            $nonGsm[] = ['char' => Fx::str($entry, 'char'), 'index' => Fx::int($entry, 'index')];
        }
        self::assertSame($nonGsm, Gsm::nonGsmCharacters($text), "{$id}: nonGsmCharacters");
        self::assertSame(Fx::strings($vector, 'validate'), Gsm::validateSms($text), "{$id}: validateSms");
    }

    public function testInvalidUtf8BytesAreNotGsm(): void
    {
        self::assertFalse(Gsm::isGsm7("ab\xFF"));
        self::assertSame([Gsm::ISSUE_NON_GSM], Gsm::validateSms("ab\xFF"));
    }
}
