<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\Order;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * An in-memory SQLite database with the plugin's own mapping (the Source and Attribution mapped
 * superclasses) and the fixture entities that make them concrete, the way Sylius' resource bundle does in an
 * application. A minimal order entity stands in for the application's order
 */
final class InMemoryEntityManager
{
    public static function create(): EntityManagerInterface
    {
        $config = ORMSetup::createConfiguration(true);
        // A driver chain per namespace, like DoctrineBundle sets up in an application: it tells Doctrine that
        // any other class (e.g. a DateTimeImmutable query parameter) isn't an entity
        $driver = new MappingDriverChain();
        foreach ([
            'Setono\SyliusConversionAttributionPlugin\Model' => dirname(__DIR__, 2) . '/src/Resources/config/doctrine/model',
            'Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture' => __DIR__ . '/Fixture',
        ] as $namespace => $directory) {
            $driver->addDriver(new SimplifiedXmlDriver([$directory => $namespace]), $namespace);
        }
        $config->setMetadataDriverImpl($driver);

        // Like Sylius does for the application's order entity
        $resolveTargetEntities = new ResolveTargetEntityListener();
        $resolveTargetEntities->addResolveTargetEntity(OrderInterface::class, Order::class, []);
        $eventManager = new EventManager();
        $eventManager->addEventSubscriber($resolveTargetEntities);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        // The public constructor works in both doctrine/orm 2.x and 3.x; the 2.x EntityManager::create()
        // factory was removed in 3.x
        $entityManager = new EntityManager($connection, $config, $eventManager);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
