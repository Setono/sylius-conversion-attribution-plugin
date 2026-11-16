<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;

final class PageViewTest extends TestCase
{
    /**
     * @test
     */
    public function it_exposes_the_page_view(): void
    {
        $pageView = new PageView('https://Shop.Example:8443/p?utm_source=google&gclid=abc', 'https://google.com/');

        self::assertSame('https://Shop.Example:8443/p?utm_source=google&gclid=abc', $pageView->url);
        self::assertSame('https://google.com/', $pageView->referrer);
        self::assertSame('shop.example', $pageView->host);
        self::assertSame('google', $pageView->getQueryParameter('utm_source'));
        self::assertSame('abc', $pageView->getQueryParameter('gclid'));
        self::assertNull($pageView->getQueryParameter('fbclid'));
    }

    /**
     * @test
     */
    public function it_ignores_non_string_query_parameters(): void
    {
        // e.g. a crafted ?utm_source[]=x, which made Symfony's InputBag throw (a 400) before
        $pageView = new PageView('https://shop.example/?utm_source[]=x&utm_medium[a]=y&utm_campaign=summer');

        self::assertNull($pageView->getQueryParameter('utm_source'));
        self::assertNull($pageView->getQueryParameter('utm_medium'));
        self::assertSame('summer', $pageView->getQueryParameter('utm_campaign'));
    }

    /**
     * @test
     */
    public function it_trims_query_parameters_and_treats_blank_ones_as_missing(): void
    {
        $pageView = new PageView('https://shop.example/?utm_source=&utm_medium=%20%20&utm_campaign=%20Summer%20Sale%20');

        self::assertNull($pageView->getQueryParameter('utm_source'));
        self::assertNull($pageView->getQueryParameter('utm_medium'));
        self::assertSame('Summer Sale', $pageView->getQueryParameter('utm_campaign'));
    }

    /**
     * @test
     */
    public function it_keeps_utf8_query_parameters_as_they_are(): void
    {
        // Emoji too: they can't be stored in a utf8mb3 column, but that's for storing, not matching
        $pageView = new PageView('https://shop.example/?utm_campaign=sommerf%C3%A6st&utm_content=%E2%82%AC%F0%9F%99%82');

        self::assertSame('sommerfæst', $pageView->getQueryParameter('utm_campaign'));
        self::assertSame('€🙂', $pageView->getQueryParameter('utm_content'));
    }

    /**
     * @test
     */
    public function it_reads_a_query_parameter_that_is_not_utf8_as_windows_1252(): void
    {
        // A link built by a Latin-1 tool
        $pageView = new PageView('https://shop.example/?utm_campaign=sommerf%E6st&utm_content=%80%9F%FF');

        self::assertSame('sommerfæst', $pageView->getQueryParameter('utm_campaign'));
        self::assertSame('€Ÿÿ', $pageView->getQueryParameter('utm_content'));
    }

    /**
     * @test
     */
    public function it_reads_the_bytes_windows_1252_leaves_undefined_as_replacement_characters(): void
    {
        $pageView = new PageView('https://shop.example/?utm_campaign=a%81b%8Dc%8Fd%90e%9Df%E6');

        self::assertSame('a�b�c�d�e�fæ', $pageView->getQueryParameter('utm_campaign'));
    }

    /**
     * @test
     *
     * @dataProvider blankReferrers
     */
    public function it_treats_a_blank_referrer_as_none(string $referrer): void
    {
        self::assertNull((new PageView('https://shop.example/', $referrer))->referrer);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankReferrers(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => [' '];
    }

    /**
     * @test
     */
    public function it_trims_the_referrer(): void
    {
        self::assertSame('https://www.google.com/', (new PageView('https://shop.example/', " https://www.google.com/\n"))->referrer);
    }

    /**
     * @test
     *
     * @dataProvider referrerHosts
     */
    public function it_exposes_the_referrer_host_lowercased(?string $referrer, ?string $expectedHost): void
    {
        self::assertSame($expectedHost, (new PageView('https://shop.example/', $referrer))->referrerHost);
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function referrerHosts(): iterable
    {
        yield 'web' => ['https://WWW.Google.com/search?q=shoes', 'www.google.com'];
        // The Gmail app; analytics tools report it as a referral from com.google.android.gm too
        yield 'app' => ['android-app://com.google.android.gm', 'com.google.android.gm'];
        yield 'none' => [null, null];
        yield 'blank' => [' ', null];
        yield 'without host' => ['not a url', null];
    }

    /**
     * @test
     *
     * @dataProvider urlsWithoutHostOrQuery
     */
    public function it_tolerates_urls_without_host_or_query(string $url): void
    {
        $pageView = new PageView($url);

        self::assertNull($pageView->host);
        self::assertNull($pageView->referrer);
        self::assertNull($pageView->getQueryParameter('utm_source'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function urlsWithoutHostOrQuery(): iterable
    {
        yield 'path only' => ['/p'];
        yield 'malformed' => ['http://'];
        yield 'empty' => [''];
    }
}
