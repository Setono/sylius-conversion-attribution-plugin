<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\CacheWarmer\ReferrersCacheWarmer;
use Setono\SyliusConversionAttributionPlugin\Parser\Medium;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParser;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

final class ReferrerParserFunctionalTest extends TestCase
{
    private static string $cacheFile = __DIR__ . '/referrers.php';

    private static PhpArrayAdapter $cache;

    public static function setUpBeforeClass(): void
    {
        self::$cache = new PhpArrayAdapter(self::$cacheFile, new FilesystemAdapter());

        $referrersCacheWarmer = new ReferrersCacheWarmer(self::$cache);
        $referrersCacheWarmer->warmUp(__DIR__);
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$cacheFile);
    }

    /**
     * @test
     *
     * @dataProvider provideKnownReferrers
     */
    public function it_parses_known_referrers(string $referrer, string $expectedMedium, string $expectedSource): void
    {
        $parser = new ReferrerParser(self::$cache);

        $parsedReferrer = $parser->parse($referrer);

        self::assertSame($expectedMedium, $parsedReferrer->medium->value);
        self::assertSame($expectedSource, $parsedReferrer->source);
    }

    /**
     * @test
     *
     * @dataProvider provideUnknownReferrers
     */
    public function it_parses_unknown_referrers(string $referrer): void
    {
        $parser = new ReferrerParser(self::$cache);

        $parsedReferrer = $parser->parse($referrer);

        self::assertSame(Medium::unknown, $parsedReferrer->medium);
    }

    /**
     * @return \Generator<array-key, array{string, string, string}>
     */
    public function provideKnownReferrers(): \Generator
    {
        yield ['https://www.google.com/search?q=hello+world', 'search', 'Google'];
        yield ['https://www.facebook.com', 'social', 'Facebook'];
        yield ['https://m.facebook.com', 'social', 'Facebook'];
        yield ['https://www.google.ac/products', 'search', 'Google Product Search'];
        yield ['https://www.google.com/imgres', 'search', 'Google Images'];
        yield ['https://images.google.com', 'search', 'Google Images'];
    }

    /**
     * @return \Generator<array-key, array{string}>
     */
    public function provideUnknownReferrers(): \Generator
    {
        yield ['https://example.com/foo/bar'];
        yield ['https://example.com'];
    }
}
