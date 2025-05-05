<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\QueryParameterBasedSourceMatcher;
use Symfony\Component\HttpFoundation\Request;

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
        $res = $matcher->match(new Request(['gclid' => '1234']));

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
        $res = $matcher->match(new Request(['source' => 'slashdot.org']));

        self::assertNotNull($res);
        self::assertSame('slashdot.org', $res->source);
        self::assertNull($res->medium);
        self::assertNull($res->campaign);
    }
}
