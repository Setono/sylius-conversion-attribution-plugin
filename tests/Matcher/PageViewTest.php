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
