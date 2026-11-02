<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Parser\Referrer;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParser;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * Runs against a small, hand written referrer database, so it doesn't need network access like
 * ReferrerParserFunctionalTest does
 */
final class ReferrerParserTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideKnownReferrers
     */
    public function it_parses_known_referrers(string $referrer, string $expectedMedium, string $expectedSource): void
    {
        $parsedReferrer = $this->parser()->parse($referrer);

        self::assertSame($expectedMedium, $parsedReferrer->medium);
        self::assertSame($expectedSource, $parsedReferrer->source);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideKnownReferrers(): iterable
    {
        yield 'host' => ['https://google.com/', 'search', 'Google'];
        yield 'host without a path' => ['https://google.com', 'search', 'Google'];
        yield 'subdomain' => ['https://www.google.com/search?q=shoes', 'search', 'Google'];
        yield 'nested subdomain' => ['https://a.b.google.com/', 'search', 'Google'];
        yield 'uppercase scheme' => ['HTTPS://www.google.com/', 'search', 'Google'];
        yield 'http' => ['http://www.google.com/', 'search', 'Google'];
        yield 'path' => ['https://www.google.com/imgres?q=shoes', 'search', 'Google Images'];
        yield 'sub path' => ['https://www.google.com/imgres/shoes/red', 'search', 'Google Images'];
        yield 'path prefix of another word' => ['https://www.google.com/imgresults', 'search', 'Google'];
        // The cache throws for keys with PSR-6 reserved characters, which only the path lookups produce
        yield 'reserved characters in the path' => ['https://www.google.com/wiki/Foo_(disambiguation)', 'search', 'Google'];
    }

    /**
     * @test
     *
     * @dataProvider provideUnknownReferrers
     */
    public function it_parses_unknown_referrers(string $referrer): void
    {
        $parsedReferrer = $this->parser()->parse($referrer);

        self::assertSame(Referrer::MEDIUM_UNKNOWN, $parsedReferrer->medium);
        self::assertNull($parsedReferrer->source);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownReferrers(): iterable
    {
        yield 'unknown host' => ['https://example.com/'];
        yield 'host without a dot' => ['http://localhost/'];
        // Subdomains are stripped down to the registrable domain, never to the bare top level domain
        yield 'top level domain' => ['https://unknown.test/'];
        yield 'cached value that is not an array' => ['https://not-an-array.test/'];
        // PSR-6 reserved characters in the path make the cache throw
        yield 'reserved characters' => ['https://example.com/wiki/Foo_(disambiguation)'];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidReferrers
     */
    public function it_parses_invalid_referrers(string $referrer): void
    {
        $parsedReferrer = $this->parser()->parse($referrer);

        self::assertSame(Referrer::MEDIUM_INVALID, $parsedReferrer->medium);
        self::assertNull($parsedReferrer->source);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidReferrers(): iterable
    {
        yield 'not a url' => ['not a url'];
        yield 'no host' => ['https:///path'];
        yield 'no scheme' => ['//google.com/'];
        yield 'unsupported scheme' => ['android-app://com.google.android.gm'];
        yield 'cached value without a medium' => ['https://no-medium.test/'];
        yield 'cached value without a source' => ['https://no-source.test/'];
        yield 'cached value with a medium that is not a string' => ['https://medium-not-a-string.test/'];
        yield 'cached value with a source that is not a string' => ['https://source-not-a-string.test/'];
    }

    private function parser(): ReferrerParser
    {
        // ArrayAdapter only validates keys in an assertion (disabled in production), unlike the pools
        // behind the real cache, so validate them here like those do
        $cache = new class() extends ArrayAdapter {
            public function getItem(mixed $key): CacheItem
            {
                CacheItem::validateKey($key);

                return parent::getItem($key);
            }
        };

        // Same format as ReferrersCacheWarmer writes: slashes in paths are stored as pipes
        foreach ([
            'google.com' => ['source' => 'Google', 'medium' => 'search'],
            'google.com|imgres' => ['source' => 'Google Images', 'medium' => 'search'],
            'test' => ['source' => 'Top level domain', 'medium' => 'search'],
            'not-an-array.test' => 'Not an array',
            'no-medium.test' => ['source' => 'No medium'],
            'no-source.test' => ['medium' => 'search'],
            'medium-not-a-string.test' => ['source' => 'Medium not a string', 'medium' => 1],
            'source-not-a-string.test' => ['source' => 1, 'medium' => 'search'],
        ] as $key => $value) {
            $cache->save($cache->getItem($key)->set($value));
        }

        return new ReferrerParser($cache);
    }
}
