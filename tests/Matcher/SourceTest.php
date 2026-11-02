<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;

final class SourceTest extends TestCase
{
    /**
     * @test
     */
    public function it_trims_its_values(): void
    {
        $source = new Source(' google ', "cpc\n", ' Summer Sale ');

        self::assertSame('google', $source->source);
        self::assertSame('cpc', $source->medium);
        self::assertSame('Summer Sale', $source->campaign);
    }

    /**
     * @test
     */
    public function it_treats_a_blank_medium_and_campaign_as_none(): void
    {
        $source = new Source('google', '', ' ');

        self::assertNull($source->medium);
        self::assertNull($source->campaign);
    }

    /**
     * @test
     *
     * @dataProvider blankValues
     */
    public function it_rejects_a_blank_source(string $source): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Source($source, 'cpc');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankValues(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => [' '];
    }
}
