<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Referrer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\CacheWarmer\ReferrersCacheWarmer;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Referrer\ReferrerDatabase;

/**
 * Downloads Snowplow's real referer database, so it needs network access
 */
final class ReferrerDatabaseFunctionalTest extends TestCase
{
    private static string $file = __DIR__ . '/referrers.php';

    public static function setUpBeforeClass(): void
    {
        (new ReferrersCacheWarmer(new ReferrerDatabase(self::$file)))->warmUp(__DIR__);
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$file);
    }

    /**
     * @test
     *
     * @dataProvider provideKnownReferrers
     */
    public function it_finds_known_referrers(string $referrer, Source $expected): void
    {
        self::assertEquals($expected, (new ReferrerDatabase(self::$file))->lookup($referrer));
    }

    /**
     * @test
     *
     * @dataProvider provideUnknownReferrers
     */
    public function it_does_not_find_unknown_referrers(string $referrer): void
    {
        self::assertNull((new ReferrerDatabase(self::$file))->lookup($referrer));
    }

    /**
     * @return \Generator<array-key, array{string, Source}>
     */
    public function provideKnownReferrers(): \Generator
    {
        yield ['https://www.google.com/', new Source('Google', 'search')];
        yield ['https://www.google.com/search?q=hello+world', new Source('Google', 'search')];
        yield ['https://www.facebook.com', new Source('Facebook', 'social')];
        yield ['https://m.facebook.com', new Source('Facebook', 'social')];
        yield ['https://www.google.ac/products', new Source('Google Product Search', 'search')];
        yield ['https://www.google.com/imgres', new Source('Google Images', 'search')];
        yield ['https://images.google.com', new Source('Google Images', 'search')];
        // Listed with the medium "unknown"
        yield ['https://maps.google.com/', new Source('Google')];
    }

    /**
     * @return \Generator<array-key, array{string}>
     */
    public function provideUnknownReferrers(): \Generator
    {
        yield ['https://example.com/foo/bar'];
        yield ['https://example.com'];
        yield ['https://unknown-referrer-example-xyz.test/wiki/Foo_(disambiguation)'];
        yield ['https://unknown-referrer-example-xyz.test/a:b@c'];
    }
}
