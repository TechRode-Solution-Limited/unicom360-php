<?php

declare(strict_types=1);

namespace Unicom360;

use Unicom360\Exception\ConfigurationException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Http\Transport;
use Unicom360\Internal\Config;
use Unicom360\Internal\Requester;
use Unicom360\Model\Health;
use Unicom360\Resource\BalanceResource;
use Unicom360\Resource\EmailResource;
use Unicom360\Resource\SmsResource;
use Unicom360\Resource\WhatsAppResource;

/**
 * The Unicom360 API client.
 *
 *     $client = new \Unicom360\Client();               // reads UNICOM360_API_KEY
 *     $client = new \Unicom360\Client(apiKey: '...');
 *
 *     $client->sms->send(from: 'Unicom360', to: '260971234567', message: 'Hello');
 *
 * Clients are immutable and can be shared; withOptions() returns a changed copy.
 */
final class Client
{
    /** This SDK's version. */
    public const VERSION = '0.1.0';

    /** The production API, the only environment (there is no sandbox). */
    public const DEFAULT_BASE_URL = Config::DEFAULT_BASE_URL;

    public readonly SmsResource $sms;

    public readonly EmailResource $email;

    public readonly WhatsAppResource $whatsapp;

    public readonly BalanceResource $balance;

    private readonly Requester $requester;

    /**
     * Every argument is optional.
     *
     * @param string|null $apiKey the API key; defaults to the UNICOM360_API_KEY environment
     *                            variable. Missing or blank: ConfigurationException.
     * @param string|null $baseUrl defaults to UNICOM360_BASE_URL, else https://api.unicom360.com.
     *                             Must be https (plain http only for localhost).
     * @param float $timeout seconds per attempt, from sending the request to receiving the body
     * @param float $connectTimeout seconds to connect, TLS included
     * @param int $maxRetries retries after the first attempt (so at most maxRetries + 1 attempts)
     * @param float $maxRetryWait the longest single wait, in seconds, the client accepts before
     *                            a retry; a rate-limit wait longer than this is not attempted
     * @param bool $autoReferenceIds give sends without a user_reference_id a generated one
     *                               ("u360-" + 32 hex), which makes SMS and WhatsApp sends safe to retry
     * @param AppInfo|array{name: string, version?: string|null, url?: string|null}|null $appInfo
     *                                                                                            your application, appended to the User-Agent
     * @param Transport|null $transport defaults to Unicom360\Http\CurlTransport
     * @param object|null $logger a PSR-3 Psr\Log\LoggerInterface (any object with debug() and
     *                            info()); nothing is logged without one
     * @param (callable(): (float|int))|null $clock test seam: the current Unix time in seconds
     * @param (callable(float): mixed)|null $sleeper test seam: waits the given seconds
     * @param (callable(): (float|int))|null $random test seam: a number in [0, 1) for jitter
     *
     * @throws ConfigurationException
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $apiKey = null,
        ?string $baseUrl = null,
        float $timeout = 60.0,
        float $connectTimeout = 10.0,
        int $maxRetries = 2,
        float $maxRetryWait = 60.0,
        bool $autoReferenceIds = false,
        AppInfo|array|null $appInfo = null,
        ?Transport $transport = null,
        ?object $logger = null,
        ?callable $clock = null,
        ?callable $sleeper = null,
        ?callable $random = null,
    ) {
        $config = Config::resolve(
            $apiKey,
            $baseUrl,
            $timeout,
            $connectTimeout,
            $maxRetries,
            $maxRetryWait,
            $autoReferenceIds,
            $appInfo,
            $transport,
            $logger,
            $clock,
            $sleeper,
            $random,
        );
        $this->requester = new Requester($config);
        $this->sms = new SmsResource($this->requester);
        $this->email = new EmailResource($this->requester);
        $this->whatsapp = new WhatsAppResource($this->requester);
        $this->balance = new BalanceResource($this->requester);
    }

    /**
     * A copy of this client with some options changed; the arguments are those of the
     * constructor, and every one left out keeps this client's value.
     *
     *     $patient = $client->withOptions(timeout: 120.0, maxRetries: 5);
     *
     * @param AppInfo|array{name: string, version?: string|null, url?: string|null}|null $appInfo
     * @param (callable(): (float|int))|null $clock
     * @param (callable(float): mixed)|null $sleeper
     * @param (callable(): (float|int))|null $random
     *
     * @throws ConfigurationException
     */
    public function withOptions(
        #[\SensitiveParameter]
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?float $timeout = null,
        ?float $connectTimeout = null,
        ?int $maxRetries = null,
        ?float $maxRetryWait = null,
        ?bool $autoReferenceIds = null,
        AppInfo|array|null $appInfo = null,
        ?Transport $transport = null,
        ?object $logger = null,
        ?callable $clock = null,
        ?callable $sleeper = null,
        ?callable $random = null,
    ): self {
        $current = $this->requester->config();

        return new self(
            apiKey: $apiKey ?? $current->apiKey->reveal(),
            baseUrl: $baseUrl ?? $current->baseUrl,
            timeout: $timeout ?? $current->timeout,
            connectTimeout: $connectTimeout ?? $current->connectTimeout,
            maxRetries: $maxRetries ?? $current->maxRetries,
            maxRetryWait: $maxRetryWait ?? $current->maxRetryWait,
            autoReferenceIds: $autoReferenceIds ?? $current->autoReferenceIds,
            appInfo: $appInfo ?? $current->appInfo,
            transport: $transport ?? $current->transport,
            logger: $logger ?? $current->log->logger(),
            clock: $clock ?? $current->clock,
            sleeper: $sleeper ?? $current->sleeper,
            random: $random ?? $current->random,
        );
    }

    /**
     * Whether the gateway is up. Needs no API key and is never billed.
     *
     * @param float|null $timeout this call's timeout in seconds
     * @param int|null $maxRetries this call's retry limit
     *
     * @throws Unicom360Exception
     */
    public function health(?float $timeout = null, ?int $maxRetries = null): Health
    {
        return Health::fromArray($this->requester->request('health', null, $timeout, $maxRetries));
    }

    /** The base URL requests go to, without a trailing slash. */
    public function baseUrl(): string
    {
        return $this->requester->config()->baseUrl;
    }

    /** The User-Agent header the client sends. */
    public function userAgent(): string
    {
        return $this->requester->config()->userAgent;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->requester->config()->__debugInfo();
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
}
