<?php

declare(strict_types=1);

namespace Unicom360\Internal;

use Unicom360\AppInfo;
use Unicom360\Client;
use Unicom360\Exception\ConfigurationException;
use Unicom360\Http\CurlTransport;
use Unicom360\Http\Transport;

/**
 * A client's resolved, validated options.
 *
 * @internal
 */
final class Config
{
    public const DEFAULT_BASE_URL = 'https://api.unicom360.com';

    /**
     * @param \Closure(): float $clock
     * @param \Closure(float): void $sleeper
     * @param \Closure(): float $random
     */
    private function __construct(
        public readonly Secret $apiKey,
        public readonly string $baseUrl,
        public readonly float $timeout,
        public readonly float $connectTimeout,
        public readonly int $maxRetries,
        public readonly float $maxRetryWait,
        public readonly bool $autoReferenceIds,
        public readonly ?AppInfo $appInfo,
        public readonly Transport $transport,
        public readonly Log $log,
        public readonly \Closure $clock,
        public readonly \Closure $sleeper,
        public readonly \Closure $random,
        public readonly string $userAgent,
    ) {}

    /**
     * @param AppInfo|array{name: string, version?: string|null, url?: string|null}|null $appInfo
     * @param (callable(): (float|int))|null $clock
     * @param (callable(float): mixed)|null $sleeper
     * @param (callable(): (float|int))|null $random
     */
    public static function resolve(
        #[\SensitiveParameter]
        ?string $apiKey,
        ?string $baseUrl,
        float $timeout,
        float $connectTimeout,
        int $maxRetries,
        float $maxRetryWait,
        bool $autoReferenceIds,
        AppInfo|array|null $appInfo,
        ?Transport $transport,
        ?object $logger,
        ?callable $clock,
        ?callable $sleeper,
        ?callable $random,
    ): self {
        $apiKey ??= Env::get('UNICOM360_API_KEY');
        if ($apiKey === null || trim($apiKey) === '') {
            throw new ConfigurationException(
                'No Unicom360 API key: pass apiKey to the Client or set the UNICOM360_API_KEY environment variable.',
            );
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $apiKey) === 1) {
            throw new ConfigurationException('The Unicom360 API key contains control characters.');
        }

        $baseUrl ??= Env::get('UNICOM360_BASE_URL') ?? self::DEFAULT_BASE_URL;
        $baseUrl = self::validateBaseUrl($baseUrl);

        if (!is_finite($timeout) || $timeout <= 0) {
            throw new ConfigurationException('timeout must be a positive number of seconds.');
        }
        if (!is_finite($connectTimeout) || $connectTimeout <= 0) {
            throw new ConfigurationException('connectTimeout must be a positive number of seconds.');
        }
        if ($maxRetries < 0) {
            throw new ConfigurationException('maxRetries must be 0 or more.');
        }
        if (!is_finite($maxRetryWait) || $maxRetryWait < 0) {
            throw new ConfigurationException('maxRetryWait must be 0 or more seconds.');
        }
        if (\is_array($appInfo)) {
            $appInfo = AppInfo::fromArray($appInfo);
        }
        if ($appInfo !== null && trim($appInfo->name) === '') {
            throw new ConfigurationException('appInfo needs a name.');
        }

        $clockFn = $clock === null
            ? static fn(): float => microtime(true)
            : static fn(): float => (float) $clock();
        $sleeperFn = $sleeper === null
            ? static function (float $seconds): void {
                if ($seconds > 0) {
                    usleep((int) round($seconds * 1_000_000));
                }
            }
        : static function (float $seconds) use ($sleeper): void {
            $sleeper($seconds);
        };
        $randomFn = $random === null
            ? static fn(): float => mt_rand() / (mt_getrandmax() + 1)
            : static fn(): float => (float) $random();

        return new self(
            new Secret($apiKey),
            $baseUrl,
            $timeout,
            $connectTimeout,
            $maxRetries,
            $maxRetryWait,
            $autoReferenceIds,
            $appInfo,
            $transport ?? new CurlTransport(),
            Log::wrap($logger),
            $clockFn,
            $sleeperFn,
            $randomFn,
            self::userAgent($appInfo),
        );
    }

    /**
     * Checks a base URL (section 3.2 of the SDK contract) and removes trailing slashes.
     */
    public static function validateBaseUrl(string $url): string
    {
        $fail = static fn(string $why): ConfigurationException => new ConfigurationException("Invalid baseUrl: {$why}.");

        if ($url === '' || preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            throw $fail('it is empty or contains whitespace');
        }
        if (str_contains($url, '?') || str_contains($url, '#')) {
            throw $fail('a query string or fragment is not allowed');
        }
        $url = rtrim($url, '/');
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw $fail('it must be an absolute URL such as https://api.unicom360.com');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw $fail('credentials in the URL are not allowed');
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if ($scheme === 'https') {
            return $url;
        }
        if ($scheme === 'http') {
            if (self::isLoopback($host)) {
                return $url;
            }
            throw $fail("plain http is only allowed for localhost, 127.0.0.0/8 and ::1, not {$host}");
        }

        throw $fail("the scheme must be https, not {$scheme}");
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'api_key' => '[REDACTED]',
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'max_retries' => $this->maxRetries,
            'max_retry_wait' => $this->maxRetryWait,
            'auto_reference_ids' => $this->autoReferenceIds,
            'user_agent' => $this->userAgent,
            'transport' => $this->transport::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new \LogicException('A Unicom360 client cannot be serialized: it holds the API key.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('A Unicom360 client cannot be unserialized.');
    }

    private static function isLoopback(string $host): bool
    {
        if ($host === 'localhost' || $host === '[::1]') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_starts_with($host, '127.');
    }

    private static function userAgent(?AppInfo $appInfo): string
    {
        $os = strtolower(PHP_OS_FAMILY);
        $arch = 'unknown';
        if (\function_exists('php_uname')) {
            $machine = php_uname('m');
            if ($machine !== '') {
                $arch = $machine;
            }
        }
        $agent = 'unicom360-php/' . Client::VERSION . ' (php ' . PHP_VERSION . "; {$os}; {$arch})";
        if ($appInfo !== null) {
            $agent .= ' ' . $appInfo->toUserAgent();
        }

        return (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $agent);
    }
}
