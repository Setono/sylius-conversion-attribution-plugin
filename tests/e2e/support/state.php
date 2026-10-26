<?php

declare(strict_types=1);

// Prints the sources tracked for a client id and the client's completed orders as JSON, so the
// end-to-end tests can assert what the browser's page views ended up as in the database

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Kernel;

require dirname(__DIR__, 2) . '/Application/config/bootstrap.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'prod', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();
$container = $kernel->getContainer();

$doctrine = $container->get('doctrine');
assert($doctrine instanceof ManagerRegistry);
$em = $doctrine->getManager();
assert($em instanceof EntityManagerInterface);

$sourceClass = $container->getParameter('setono_sylius_conversion_attribution.model.source.class');
$orderClass = $container->getParameter('sylius.model.order.class');
assert(is_string($sourceClass) && is_string($orderClass));

$clientId = $argv[1] ?? '';

$sources = $em->createQuery(sprintf(
    'SELECT s.source, s.medium, s.campaign, s.page, s.referrer, s.ip, s.userAgent FROM %s s WHERE s.clientId = :clientId ORDER BY s.id',
    $sourceClass,
))->setParameter('clientId', $clientId)->getArrayResult();

$orders = $em->createQuery(sprintf(
    'SELECT o.id, o.number FROM %s o WHERE o.clientId = :clientId AND o.checkoutCompletedAt IS NOT NULL ORDER BY o.id',
    $orderClass,
))->setParameter('clientId', $clientId)->getArrayResult();

echo json_encode(['sources' => $sources, 'orders' => $orders], \JSON_THROW_ON_ERROR);
