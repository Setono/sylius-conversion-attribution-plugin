<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\UtmQueryParameterBasedSourceMatcher;
use Symfony\Component\HttpFoundation\Request;

final class UtmQueryParameterBasedSourceMatcherTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches(): void
    {
        $matcher = new UtmQueryParameterBasedSourceMatcher();
        $res = $matcher->match(new Request([
            'utm_source' => 'affiliate-site.com', 'utm_medium' => 'affiliate', 'utm_campaign' => 'Summer Sale']));

        self::assertNotNull($res);
        self::assertSame('affiliate-site.com', $res->source);
        self::assertSame('affiliate', $res->medium);
        self::assertSame('Summer Sale', $res->campaign);
    }
}
