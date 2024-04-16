<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Model\Source;

final class SourceTest extends TestCase
{
    /**
     * @test
     */
    public function it_lowercases_source_and_medium(): void
    {
        $source = new Source();
        $source->setSource('Google');
        $source->setMedium('Organic');

        self::assertSame('google', $source->getSource());
        self::assertSame('organic', $source->getMedium());
    }
}
