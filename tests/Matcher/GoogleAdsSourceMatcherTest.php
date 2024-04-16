<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\GoogleAdsSourceMatcher;
use Symfony\Component\HttpFoundation\Request;

final class GoogleAdsSourceMatcherTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches(): void
    {
        $matcher = new GoogleAdsSourceMatcher();
        $res = $matcher->match(new Request(['gclid' => '1234']));

        self::assertNotNull($res);
        self::assertSame('google', $res->source);
        self::assertSame('cpc', $res->medium);
    }
}
