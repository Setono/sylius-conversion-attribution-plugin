<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\CompositeSourceMatcher;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;

final class CompositeSourceMatcherTest extends TestCase
{
    /**
     * @test
     */
    public function it_returns_the_first_match(): void
    {
        $pageView = new PageView('https://shop.example/');
        $match = new Source('google', 'cpc');

        $composite = new CompositeSourceMatcher();
        $composite->add($this->matcher($pageView, null));
        $composite->add($this->matcher($pageView, $match));

        $last = $this->createMock(SourceMatcherInterface::class);
        $last->expects(self::never())->method('match');
        $composite->add($last);

        self::assertSame($match, $composite->match($pageView));
    }

    /**
     * @test
     */
    public function it_returns_null_if_no_matcher_matches(): void
    {
        $pageView = new PageView('https://shop.example/');

        $composite = new CompositeSourceMatcher();
        $composite->add($this->matcher($pageView, null));
        $composite->add($this->matcher($pageView, null));

        self::assertNull($composite->match($pageView));
    }

    /**
     * @test
     */
    public function it_returns_null_without_matchers(): void
    {
        self::assertNull((new CompositeSourceMatcher())->match(new PageView('https://shop.example/')));
    }

    private function matcher(PageView $pageView, ?Source $result): SourceMatcherInterface
    {
        $matcher = $this->createMock(SourceMatcherInterface::class);
        $matcher->expects(self::once())->method('match')->with($pageView)->willReturn($result);

        return $matcher;
    }
}
