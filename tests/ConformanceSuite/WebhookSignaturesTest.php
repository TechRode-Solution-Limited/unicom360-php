<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Exception\SignatureVerificationException;
use Unicom360\Tests\Support\Fx;
use Unicom360\Webhook;

/**
 * conformance/webhooks/signatures.json: valid, tampered, wrong-secret, decoded-secret, stale,
 * future, missing-header and malformed deliveries.
 */
final class WebhookSignaturesTest extends TestCase
{
    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function vectors(): array
    {
        return Fx::vectors('webhooks/signatures.json');
    }

    /**
     * @param array<mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testVerify(array $vector): void
    {
        Fx::assertKnownKeys($vector, ['id', 'description', 'secret', 'headers', 'body_base64', 'body_text', 'now_unix', 'tolerance', 'expect'], 'signatures.json vector');
        $id = Fx::str($vector, 'id');
        $body = base64_decode(Fx::str($vector, 'body_base64'), true);
        self::assertIsString($body, "{$id}: body_base64 decodes");
        self::assertSame(Fx::str($vector, 'body_text'), $body, "{$id}: body_text is the same bytes");
        $expect = Fx::arr($vector, 'expect');
        Fx::assertKnownKeys($expect, ['valid', 'reason'], "{$id} expect");

        $error = null;
        try {
            Webhook::verify(
                $body,
                Fx::arr($vector, 'headers'),
                Fx::str($vector, 'secret'),
                Fx::int($vector, 'tolerance'),
                Fx::num($vector, 'now_unix'),
            );
        } catch (SignatureVerificationException $e) {
            $error = $e;
        }

        if (Fx::bool($expect, 'valid')) {
            self::assertNull($error, "{$id}: expected valid, got " . ($error === null ? '' : $error->getReason()));

            return;
        }
        self::assertNotNull($error, "{$id}: expected a SignatureVerificationException");
        self::assertSame(Fx::str($expect, 'reason'), $error->getReason(), "{$id}: reason");
        self::assertSame('signature_verification_error', $error->getType());
        self::assertStringNotContainsString(Fx::str($vector, 'secret'), $error->getMessage(), "{$id}: the secret never appears");
    }
}
