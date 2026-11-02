<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\Order;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\StoredAttribution;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\InMemoryEntityManager;
use Setono\SyliusConversionAttributionPlugin\Twig\Runtime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Lets the --DATA-- sections of Twig integration test fixtures create orders, and their attributions, in an
 * in-memory database that the plugin's Twig runtime reads from
 */
trait AttributedOrders
{
    private static EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::$em = InMemoryEntityManager::create();
    }

    /**
     * @param array{source: string, medium?: string, campaign?: string, referrer?: string, landingPage?: string, trackedAt?: string}|null $attribution
     */
    public static function order(?string $clientId, ?array $attribution = null): Order
    {
        $order = Order::completed($clientId, new \DateTimeImmutable('2026-10-26 12:00:00'));
        self::$em->persist($order);

        if (null !== $attribution) {
            $storedAttribution = new StoredAttribution();
            $storedAttribution->setOrder($order);
            $storedAttribution->setSource($attribution['source']);
            $storedAttribution->setMedium($attribution['medium'] ?? null);
            $storedAttribution->setCampaign($attribution['campaign'] ?? null);
            $storedAttribution->setReferrer($attribution['referrer'] ?? null);
            $storedAttribution->setLandingPage($attribution['landingPage'] ?? null);
            $storedAttribution->setTrackedAt(new \DateTimeImmutable($attribution['trackedAt'] ?? '2026-10-26 11:00:00'));
            self::$em->persist($storedAttribution);
        }

        self::$em->flush();

        return $order;
    }

    protected function getRuntimeLoaders(): array
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(self::$em);

        return [new FactoryRuntimeLoader([
            Runtime::class => static fn (): Runtime => new Runtime($registry, StoredAttribution::class),
        ])];
    }
}
