<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Model;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Tests\Model\Fixture\Customer;
use Setono\SyliusConversionAttributionPlugin\Tests\Model\Fixture\Order;

/**
 * Host applications map their Order and Customer entities with attributes (Sylius-Standard 1.13+) or, in older
 * apps, annotations, and only the driver they use reads the traits. The test application maps with attributes,
 * so this is what covers apps that use annotations
 */
final class TraitMappingTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider drivers
     */
    public function it_maps_the_client_id_of_an_order(MappingDriver $driver): void
    {
        $metadata = self::loadMetadata($driver, Order::class);

        self::assertSame('string', $metadata->getTypeOfField('clientId'));
        self::assertTrue($metadata->isNullable('clientId'));
    }

    /**
     * @test
     *
     * @dataProvider drivers
     */
    public function it_maps_the_client_ids_of_a_customer(MappingDriver $driver): void
    {
        $metadata = self::loadMetadata($driver, Customer::class);

        self::assertSame('json', $metadata->getTypeOfField('clientIds'));
        self::assertTrue($metadata->isNullable('clientIds'));
    }

    /**
     * @return iterable<string, array{MappingDriver}>
     */
    public static function drivers(): iterable
    {
        yield 'attributes' => [new AttributeDriver([])];
        yield 'annotations' => [new AnnotationDriver(new AnnotationReader())];
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private static function loadMetadata(MappingDriver $driver, string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $driver->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
