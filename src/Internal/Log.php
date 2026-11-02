<?php

declare(strict_types=1);

namespace Unicom360\Internal;

use Unicom360\Exception\ConfigurationException;

/**
 * Wraps the optional PSR-3 logger. Any object with debug() and info() methods taking a message
 * and a context array works, so psr/log is not required.
 *
 * @internal
 */
final class Log
{
    private function __construct(private readonly ?object $logger) {}

    public static function wrap(?object $logger): self
    {
        if ($logger !== null && (!method_exists($logger, 'debug') || !method_exists($logger, 'info'))) {
            throw new ConfigurationException('logger must be a PSR-3 LoggerInterface (an object with debug() and info() methods).');
        }

        return new self($logger);
    }

    public function logger(): ?object
    {
        return $this->logger;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context): void
    {
        if ($this->logger !== null && method_exists($this->logger, 'debug')) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context): void
    {
        if ($this->logger !== null && method_exists($this->logger, 'info')) {
            $this->logger->info($message, $context);
        }
    }
}
