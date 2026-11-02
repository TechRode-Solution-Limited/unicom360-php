<?php

declare(strict_types=1);

namespace Unicom360;

/**
 * Identifies the application using the SDK. It is appended to the User-Agent, so the
 * Unicom360 team can tell integrations apart when helping with support requests.
 *
 *     new Client(appInfo: new AppInfo('shopsync', '2.3.0', 'https://shopsync.example'));
 */
final class AppInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $version = null,
        public readonly ?string $url = null,
    ) {}

    /**
     * @param array{name: string, version?: string|null, url?: string|null} $info
     */
    public static function fromArray(array $info): self
    {
        return new self($info['name'], $info['version'] ?? null, $info['url'] ?? null);
    }

    /** The User-Agent suffix: name[/version][ (+url)]. */
    public function toUserAgent(): string
    {
        $text = $this->name;
        if ($this->version !== null && $this->version !== '') {
            $text .= '/' . $this->version;
        }
        if ($this->url !== null && $this->url !== '') {
            $text .= ' (+' . $this->url . ')';
        }

        return $text;
    }
}
