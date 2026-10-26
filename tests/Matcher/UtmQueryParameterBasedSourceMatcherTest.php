<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\UtmQueryParameterBasedSourceMatcher;

final class UtmQueryParameterBasedSourceMatcherTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches(): void
    {
        $matcher = new UtmQueryParameterBasedSourceMatcher();
        $res = $matcher->match(new PageView('https://shop.example/?utm_source=affiliate-site.com&utm_medium=affiliate&utm_campaign=Summer+Sale'));

        self::assertNotNull($res);
        self::assertSame('affiliate-site.com', $res->source);
        self::assertSame('affiliate', $res->medium);
        self::assertSame('Summer Sale', $res->campaign);
    }

    /**
     * @test
     */
    public function it_does_not_match_without_a_utm_source(): void
    {
        self::assertNull((new UtmQueryParameterBasedSourceMatcher())->match(new PageView('https://shop.example/?utm_medium=cpc&utm_campaign=summer')));
    }

    /**
     * @test
     */
    public function it_matches_without_medium_and_campaign(): void
    {
        $res = (new UtmQueryParameterBasedSourceMatcher())->match(new PageView('https://shop.example/?utm_source=newsletter'));

        self::assertNotNull($res);
        self::assertSame('newsletter', $res->source);
        self::assertNull($res->medium);
        self::assertNull($res->campaign);
    }
}
