<?php

declare(strict_types=1);

namespace Unicom360\Tests\ConformanceSuite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Unicom360\Tests\Support\Fx;

/**
 * The vendored fixtures are the pinned, unmodified copy, and every file is run by a harness.
 */
final class FixturesTest extends TestCase
{
    /** Every fixture file, and the harness test class that runs its vectors. */
    private const FILES = [
        'requests.json' => RequestsTest::class,
        'responses.json' => ResponsesTest::class,
        'retries.json' => RetriesTest::class,
        'error-codes.json' => ErrorCodesTest::class,
        'callbacks.json' => CallbacksTest::class,
        'gsm7.json' => Gsm7Test::class,
        'webhooks/signatures.json' => WebhookSignaturesTest::class,
        'webhooks/events.json' => WebhookEventsTest::class,
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function files(): array
    {
        $out = [];
        foreach (array_keys(self::FILES) as $file) {
            $out[$file] = [$file];
        }

        return $out;
    }

    public function testTreeHashMatchesVersion(): void
    {
        $version = $this->version();
        self::assertSame('v0.1.0', $version['tag'] ?? null, 'pinned tag');
        self::assertSame('0.1.0', $version['version'] ?? null, 'fixture version');

        // conformance/README.md, "Vendoring into an SDK": sha256 of a sha256sum-style listing of
        // every file except VERSION, sorted by relative path in byte order.
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(Fx::DIR, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $relative = str_replace(\DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), \strlen(Fx::DIR) + 1));
            if ($relative !== 'VERSION') {
                $files[] = $relative;
            }
        }
        sort($files, SORT_STRING);
        $listing = '';
        foreach ($files as $relative) {
            $listing .= hash_file('sha256', Fx::DIR . '/' . $relative) . '  ' . $relative . "\n";
        }

        self::assertSame($version['sha256'] ?? null, hash('sha256', $listing), 'the vendored fixtures were modified; re-run scripts/sync-conformance');
    }

    #[DataProvider('files')]
    public function testFileIsRunByAHarness(string $file): void
    {
        $data = Fx::file($file);
        self::assertSame($this->version()['version'] ?? null, $data['version'] ?? null, "{$file}: version");
        $class = self::FILES[$file];
        self::assertTrue(method_exists($class, 'vectors'), "{$class} has a data provider");
        $provided = $class::vectors();
        self::assertCount(\count(Fx::list($data, 'vectors')), $provided, "{$file}: every vector is provided");
    }

    public function testNoFixtureFileIsLeftOut(): void
    {
        $found = [];
        foreach (['', 'webhooks/'] as $dir) {
            foreach (glob(Fx::DIR . '/' . $dir . '*.json') ?: [] as $path) {
                $found[] = $dir . basename($path);
            }
        }
        sort($found);
        $known = array_keys(self::FILES);
        sort($known);
        self::assertSame($known, $found, 'a new fixture file needs a harness');
    }

    /**
     * @return array<string, string>
     */
    private function version(): array
    {
        $text = file_get_contents(Fx::DIR . '/VERSION');
        self::assertIsString($text);
        $out = [];
        foreach (explode("\n", $text) as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
