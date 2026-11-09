<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Repository;

use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Repository\AttributionRepositoryInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AttributionRepositoryTest extends KernelTestCase
{
    use PageViewsAndOrders;

    /**
     * @test
     */
    public function it_finds_completed_tracked_orders_without_an_attribution_oldest_first_up_to_the_limit(): void
    {
        $first = self::order('first', new \DateTimeImmutable('-3 days'));
        $second = self::order('second', new \DateTimeImmutable('-2 days'));
        self::attribution(self::order('attributed', new \DateTimeImmutable('-2 days')));
        self::order(null, new \DateTimeImmutable('-2 days'));
        self::order('in-checkout', null);
        $third = self::order('third', new \DateTimeImmutable('-1 day'));

        self::assertSame([$first->getId(), $second->getId()], $this->idsOf(self::repository()->findOrdersToAttribute(2)));
        self::assertSame([$first->getId(), $second->getId(), $third->getId()], $this->idsOf(self::repository()->findOrdersToAttribute(10)));
    }

    /**
     * @test
     */
    public function it_finds_only_orders_completed_since_a_given_time(): void
    {
        self::order('old', new \DateTimeImmutable('-40 days'));
        $recent = self::order('recent', new \DateTimeImmutable('-1 day'));

        self::assertSame([$recent->getId()], $this->idsOf(self::repository()->findOrdersToAttribute(10, new \DateTimeImmutable('-30 days'))));
    }

    protected static function container(): ContainerInterface
    {
        return self::getContainer();
    }

    private static function repository(): AttributionRepositoryInterface
    {
        $repository = self::getContainer()->get(AttributionRepositoryInterface::class);
        self::assertInstanceOf(AttributionRepositoryInterface::class, $repository);

        return $repository;
    }

    /**
     * @param list<OrderInterface> $orders
     *
     * @return list<mixed>
     */
    private function idsOf(array $orders): array
    {
        return array_map(static fn (OrderInterface $order): mixed => $order->getId(), $orders);
    }
}
