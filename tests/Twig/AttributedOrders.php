<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Twig;

use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Kernel;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Setono\SyliusConversionAttributionPlugin\Twig\Runtime;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Boots the test application for Twig integration tests, so the --DATA-- sections of their fixtures can
 * create orders and attributions in its database, and the plugin's Twig runtime reads them from there
 */
trait AttributedOrders
{
    use PageViewsAndOrders;

    private static ?Kernel $kernel = null;

    protected function setUp(): void
    {
        self::$kernel = new Kernel('test', false);
        self::$kernel->boot();
    }

    protected function tearDown(): void
    {
        self::$kernel?->shutdown();
        self::$kernel = null;
    }

    /**
     * An order completed on 2026-10-26 at noon, attributed if $attribution is given
     *
     * @param array{source: string, medium?: string, campaign?: string, referrer?: string, landingPage?: string, trackedAt?: string}|null $attribution
     */
    public static function attributedOrder(?string $clientId, ?array $attribution = null): OrderInterface
    {
        $order = self::order($clientId, new \DateTimeImmutable('2026-10-26 12:00:00'));
        if (null !== $attribution) {
            self::attribution($order, $attribution['source'], $attribution);
        }

        return $order;
    }

    protected static function container(): ContainerInterface
    {
        self::assertNotNull(self::$kernel);
        $container = self::$kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    protected function getRuntimeLoaders(): array
    {
        $runtime = self::container()->get(Runtime::class);
        self::assertInstanceOf(Runtime::class, $runtime);

        return [new FactoryRuntimeLoader([Runtime::class => static fn (): Runtime => $runtime])];
    }
}
