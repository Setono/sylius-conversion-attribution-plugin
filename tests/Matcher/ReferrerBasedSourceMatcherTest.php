<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\ReferrerBasedSourceMatcher;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Referrer\ReferrerDatabase;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\TemporaryDirectory;

final class ReferrerBasedSourceMatcherTest extends TestCase
{
    use TemporaryDirectory;

    private ReferrerBasedSourceMatcher $matcher;

    protected function setUp(): void
    {
        $referrerDatabase = new ReferrerDatabase($this->dir . '/referrers.php');
        $referrerDatabase->build((string) json_encode([
            'search' => ['Google' => ['domains' => ['google.com']]],
            // Snowplow lists e.g. Google Maps with the medium "unknown"
            'unknown' => ['Google' => ['domains' => ['maps.google.com']]],
        ]));

        $this->matcher = new ReferrerBasedSourceMatcher($referrerDatabase);
    }

    /**
     * @test
     */
    public function it_matches_a_site_in_the_referrer_database(): void
    {
        self::assertEquals(new Source('Google', 'search'), $this->matcher->match(new PageView('https://shop.example/', 'https://www.google.com/')));
    }

    /**
     * @test
     */
    public function it_matches_a_site_in_the_referrer_database_without_a_medium_as_a_referral(): void
    {
        self::assertEquals(new Source('Google', 'referral'), $this->matcher->match(new PageView('https://shop.example/', 'https://maps.google.com/')));
    }

    /**
     * @test
     */
    public function it_matches_a_site_missing_from_the_referrer_database_as_a_referral_from_its_host(): void
    {
        self::assertEquals(new Source('blog.example', 'referral'), $this->matcher->match(new PageView('https://shop.example/', 'https://blog.example/post')));
    }

    /**
     * @test
     */
    public function it_uses_the_configured_referral_medium(): void
    {
        $matcher = new ReferrerBasedSourceMatcher(new ReferrerDatabase($this->dir . '/referrers.php'), 'link');

        self::assertEquals(new Source('Google', 'link'), $matcher->match(new PageView('https://shop.example/', 'https://maps.google.com/')));
        self::assertEquals(new Source('blog.example', 'link'), $matcher->match(new PageView('https://shop.example/', 'https://blog.example/post')));
    }

    /**
     * @test
     *
     * @dataProvider sameHostPageViews
     */
    public function it_does_not_match_a_referrer_from_the_same_host(PageView $pageView): void
    {
        self::assertNull($this->matcher->match($pageView));
    }

    /**
     * @return iterable<string, array{PageView}>
     */
    public static function sameHostPageViews(): iterable
    {
        yield 'page host in upper case' => [new PageView('https://SHOP.example/cart', 'https://shop.example/product')];
        yield 'referrer host in upper case' => [new PageView('https://shop.example/cart', 'https://SHOP.example/product')];
        yield 'page host with www.' => [new PageView('https://www.shop.example/', 'https://shop.example/x')];
        yield 'referrer host with www.' => [new PageView('https://shop.example/', 'https://www.shop.example/x')];
        yield 'page host with www. in upper case' => [new PageView('https://WWW.Shop.example/', 'https://shop.example/')];
        yield 'both hosts with www.' => [new PageView('https://www.shop.example/cart', 'https://www.shop.example/product')];
    }

    /**
     * @test
     *
     * @dataProvider subdomainsOfTheShop
     */
    public function it_matches_another_subdomain_of_the_shop_as_a_referral(string $referrer, string $source): void
    {
        self::assertEquals(new Source($source, 'referral'), $this->matcher->match(new PageView('https://shop.example/', $referrer)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function subdomainsOfTheShop(): iterable
    {
        yield 'subdomain' => ['https://blog.shop.example/', 'blog.shop.example'];
        yield 'subdomain starting with www but not www.' => ['https://www2.shop.example/', 'www2.shop.example'];
    }

    /**
     * @test
     */
    public function it_keeps_www_in_the_host_of_a_referrer_missing_from_the_referrer_database(): void
    {
        self::assertEquals(new Source('www.other.example', 'referral'), $this->matcher->match(new PageView('https://www.shop.example/', 'https://www.other.example/post')));
    }

    /**
     * @test
     *
     * @dataProvider unusableReferrers
     */
    public function it_does_not_match_without_a_usable_referrer(?string $referrer): void
    {
        self::assertNull($this->matcher->match(new PageView('https://shop.example/', $referrer)));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unusableReferrers(): iterable
    {
        yield 'none' => [null];
        yield 'without host' => ['not a url'];
    }
}
