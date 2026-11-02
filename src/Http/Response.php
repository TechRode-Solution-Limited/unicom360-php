<?php

declare(strict_types=1);

namespace Unicom360\Http;

/**
 * One HTTP response, as a Transport returns it.
 */
final class Response
{
    /** @var array<string, string> */
    public readonly array $headers;

    /**
     * @param int $status the HTTP status code
     * @param array<string, string|list<string>> $headers header name to value; names are
     *                                                   lower-cased and repeated values joined with ", "
     * @param string $body the whole body as text (empty when there was none)
     */
    public function __construct(
        public readonly int $status,
        array $headers,
        public readonly string $body,
    ) {
        $normalised = [];
        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            $text = \is_array($value) ? implode(', ', $value) : $value;
            $normalised[$key] = isset($normalised[$key]) ? $normalised[$key] . ', ' . $text : $text;
        }
        $this->headers = $normalised;
    }

    /** The header's value (names are case-insensitive), or null when absent. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
