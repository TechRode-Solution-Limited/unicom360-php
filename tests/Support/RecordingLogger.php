<?php

declare(strict_types=1);

namespace Unicom360\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * A PSR-3 logger that keeps every record.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param mixed $level
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => \is_string($level) ? $level : 'unknown', 'message' => (string) $message, 'context' => $context];
    }
}
