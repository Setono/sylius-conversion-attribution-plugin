<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactory;
use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class SourceFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_a_source_through_the_decorated_factory(): void
    {
        $source = new Source();

        self::assertSame($source, (new SourceFactory($this->decoratedFactory($source)))->createNew());
    }

    /**
     * @test
     */
    public function it_throws_if_the_decorated_factory_does_not_create_a_source(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SourceFactory($this->decoratedFactory(new \stdClass())))->createNew();
    }

    private function decoratedFactory(object $created): FactoryInterface
    {
        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createNew')->willReturn($created);

        return $factory;
    }
}
