<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\GenericSourceMatcher;
use Symfony\Component\HttpFoundation\Request;

final class GenericSourceMatcherTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches(): void
    {
        $matcher = new GenericSourceMatcher([
            'ref',
        ]);

        $request = new Request([
            'ref' => 'test',
        ]);

        $res = $matcher->match($request);
        self::assertNotNull($res);
        self::assertSame('test', $res->source);
    }

    /**
     * @test
     */
    public function it_does_not_match(): void
    {
        $matcher = new GenericSourceMatcher([
            'ref',
        ]);

        $request = new Request([
            'source' => 'test',
        ]);

        $res = $matcher->match($request);
        self::assertNull($res);
    }
}
