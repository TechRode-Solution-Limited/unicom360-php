<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\ErrorCode;
use Unicom360\Tests\Support\Fx;

/**
 * conformance/error-codes.json: raw gateway messages and their normalised code.
 */
final class ErrorCodesTest extends TestCase
{
    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('error-codes.json');
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testNormalize(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'message', 'code', 'source', 'note'], 'error-codes.json vector');
        self::assertSame(Fx::str($vector, 'code'), ErrorCode::normalize($vector['message'] ?? null), Fx::str($vector, 'id'));
    }

    public function testNonStringsAreUnknown(): void
    {
        self::assertSame('UNKNOWN', ErrorCode::normalize(null));
        self::assertSame('UNKNOWN', ErrorCode::normalize(['INSUFFICIENT_BUNDLE']));
        self::assertSame('UNKNOWN', ErrorCode::normalize(42));
    }

    public function testTrailingNewlineIsNotACode(): void
    {
        self::assertSame('UNKNOWN', ErrorCode::normalize("INSUFFICIENT_BUNDLE\n"));
    }
}
