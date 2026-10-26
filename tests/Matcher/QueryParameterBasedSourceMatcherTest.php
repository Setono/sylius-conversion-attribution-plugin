<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\QueryParameterBasedSourceMatcher;

final class QueryParameterBasedSourceMatcherTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches1(): void
    {
        $matcher = new QueryParameterBasedSourceMatcher([
            ['matches' => ['gclid'], 'source' => 'google', 'medium' => 'cpc', 'campaign' => 'Summer Sale'],
        ]);
        $res = $matcher->match(new PageView('https://shop.example/?gclid=1234'));

        self::assertNotNull($res);
        self::assertSame('google', $res->source);
        self::assertSame('cpc', $res->medium);
        self::assertSame('Summer Sale', $res->campaign);
    }

    /**
     * @test
     */
    public function it_matches2(): void
    {
        $matcher = new QueryParameterBasedSourceMatcher([
            ['matches' => ['source'], 'source' => null, 'medium' => null, 'campaign' => null],
        ]);
        $res = $matcher->match(new PageView('https://shop.example/?source=slashdot.org'));

        self::assertNotNull($res);
        self::assertSame('slashdot.org', $res->source);
        self::assertNull($res->medium);
        self::assertNull($res->campaign);
    }

    /**
     * @test
     */
    public function it_skips_empty_and_non_string_values(): void
    {
        $matcher = new QueryParameterBasedSourceMatcher([
            ['matches' => ['gclid', 'wbraid'], 'source' => 'google', 'medium' => 'cpc', 'campaign' => null],
            ['matches' => ['fbclid'], 'source' => 'facebook', 'medium' => 'cpc', 'campaign' => null],
        ]);

        self::assertNull($matcher->match(new PageView('https://shop.example/?gclid=&wbraid[]=1')));

        $res = $matcher->match(new PageView('https://shop.example/?gclid=&fbclid=abc'));
        self::assertNotNull($res);
        self::assertSame('facebook', $res->source);
    }
}
