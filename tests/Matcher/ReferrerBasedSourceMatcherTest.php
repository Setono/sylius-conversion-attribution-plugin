<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\ReferrerBasedSourceMatcher;
use Setono\SyliusConversionAttributionPlugin\Parser\Referrer;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParserInterface;

final class ReferrerBasedSourceMatcherTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches_a_known_referrer(): void
    {
        $res = $this->matcher(new Referrer(Referrer::MEDIUM_SEARCH, 'Google'))
            ->match(new PageView('https://shop.example/', 'https://www.google.com/'));

        self::assertNotNull($res);
        self::assertSame('Google', $res->source);
        self::assertSame(Referrer::MEDIUM_SEARCH, $res->medium);
    }

    /**
     * @test
     */
    public function it_matches_an_unknown_referrer_from_another_host_as_a_referral(): void
    {
        $res = $this->matcher(new Referrer(Referrer::MEDIUM_UNKNOWN))
            ->match(new PageView('https://shop.example/', 'https://blog.example/post'));

        self::assertNotNull($res);
        self::assertSame('blog.example', $res->source);
        self::assertSame('referral', $res->medium);
    }

    /**
     * @test
     *
     * @dataProvider unusableParsedReferrers
     */
    public function it_falls_back_to_the_referrer_host_if_the_parsed_referrer_is_unusable(Referrer $parsedReferrer): void
    {
        // A custom parser may return a medium without a source, or a source for an invalid referrer
        $res = $this->matcher($parsedReferrer)
            ->match(new PageView('https://shop.example/', 'https://www.google.com/'));

        self::assertNotNull($res);
        self::assertSame('www.google.com', $res->source);
        self::assertSame('referral', $res->medium);
    }

    /**
     * @return iterable<string, array{Referrer}>
     */
    public static function unusableParsedReferrers(): iterable
    {
        yield 'medium without a source' => [new Referrer(Referrer::MEDIUM_SEARCH)];
        yield 'invalid with a source' => [new Referrer(Referrer::MEDIUM_INVALID, 'Google')];
        yield 'unknown with a source' => [new Referrer(Referrer::MEDIUM_UNKNOWN, 'Google')];
    }

    /**
     * @test
     *
     * @dataProvider sameHostPageViews
     */
    public function it_does_not_match_a_referrer_from_the_same_host(PageView $pageView): void
    {
        self::assertNull($this->matcher(new Referrer(Referrer::MEDIUM_UNKNOWN))->match($pageView));
    }

    /**
     * @return iterable<string, array{PageView}>
     */
    public static function sameHostPageViews(): iterable
    {
        yield 'page host in upper case' => [new PageView('https://SHOP.example/cart', 'https://shop.example/product')];
        yield 'referrer host in upper case' => [new PageView('https://shop.example/cart', 'https://SHOP.example/product')];
    }

    /**
     * @test
     *
     * @dataProvider unusableReferrers
     */
    public function it_does_not_match_without_a_usable_referrer(?string $referrer): void
    {
        self::assertNull($this->matcher(new Referrer(Referrer::MEDIUM_INVALID))
            ->match(new PageView('https://shop.example/', $referrer)));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unusableReferrers(): iterable
    {
        yield 'none' => [null];
        yield 'without host' => ['not a url'];
    }

    private function matcher(Referrer $parsed): ReferrerBasedSourceMatcher
    {
        $parser = $this->createMock(ReferrerParserInterface::class);
        $parser->method('parse')->willReturn($parsed);

        return new ReferrerBasedSourceMatcher($parser);
    }
}
