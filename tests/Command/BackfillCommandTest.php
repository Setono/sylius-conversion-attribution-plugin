<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class BackfillCommandTest extends KernelTestCase
{
    use PageViewsAndOrders;

    /**
     * @test
     */
    public function it_attributes_completed_orders_as_of_their_checkout(): void
    {
        $order = self::order('client', new \DateTimeImmutable('2026-10-01 12:00:00'));
        self::trackPageView('client', 'google', new \DateTimeImmutable('2026-09-30 12:00:00'));
        // after the checkout, so the order must not be credited to it
        self::trackPageView('client', 'facebook', new \DateTimeImmutable('2026-10-05 12:00:00'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertSame(['google'], $this->attributedSources($order));
        self::assertStringContainsString('[OK] Attributed 1 of the 1 orders without an attribution', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_skips_orders_that_cannot_or_need_not_be_attributed(): void
    {
        $attributed = self::order('attributed', new \DateTimeImmutable('2026-10-01 12:00:00'));
        self::trackPageView('attributed', 'google', new \DateTimeImmutable('2026-09-30 12:00:00'));
        self::attribution($attributed, 'newsletter');

        $untracked = self::order(null, new \DateTimeImmutable('2026-10-01 12:00:00'));

        $inCheckout = self::order('in-checkout', null);
        self::trackPageView('in-checkout', 'google', new \DateTimeImmutable('-1 hour'));

        $withoutPageViews = self::order('without-page-views', new \DateTimeImmutable('2026-10-01 12:00:00'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertSame(['newsletter'], $this->attributedSources($attributed));
        self::assertSame([], $this->attributedSources($untracked));
        self::assertSame([], $this->attributedSources($inCheckout));
        self::assertSame([], $this->attributedSources($withoutPageViews));
        // only the order without page views needed an attribution
        self::assertStringContainsString('[OK] Attributed 0 of the 1 orders without an attribution', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_attributes_orders_in_batches(): void
    {
        $first = self::order('first', new \DateTimeImmutable('2026-10-01 12:00:00'));
        self::trackPageView('first', 'google', new \DateTimeImmutable('2026-09-30 12:00:00'));
        // They stay without an attribution, so the next batch must not start over with them
        for ($i = 0; $i < 99; ++$i) {
            self::order('without-page-views', new \DateTimeImmutable('2026-10-01 12:00:00'));
        }
        // the 101st order, so it's in the second batch (of 100)
        $last = self::order('last', new \DateTimeImmutable('2026-10-01 12:00:00'));
        self::trackPageView('last', 'facebook', new \DateTimeImmutable('2026-09-30 12:00:00'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        // each batch is flushed and cleared, so a large backfill doesn't hold every order in memory
        self::assertSame(0, self::entityManager()->getUnitOfWork()->size());
        self::assertSame(['google'], $this->attributedSources($first));
        self::assertSame(['facebook'], $this->attributedSources($last));
        // the progress bar is finished, followed by a blank line and the result
        self::assertMatchesRegularExpression('# 101/101 \[.+\] 100%\n\n \[OK\] Attributed 2 of the 101 orders without an attribution#u', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_reports_success_when_there_is_nothing_to_attribute(): void
    {
        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Attributing completed orders', $tester->getDisplay());
        self::assertStringContainsString('[OK] No orders to attribute', $tester->getDisplay());
    }

    protected static function container(): ContainerInterface
    {
        return self::getContainer();
    }

    /**
     * @return list<string|null>
     */
    private function attributedSources(OrderInterface $order): array
    {
        self::entityManager()->clear();

        return array_map(static fn (AttributionInterface $attribution): ?string => $attribution->getSource(), self::attributionsOf($order));
    }

    private function execute(): CommandTester
    {
        // getContainer() boots the kernel, unless the test already did
        self::getContainer();

        $tester = new CommandTester((new Application(self::$kernel))->find('setono:sylius-conversion-attribution:backfill'));
        $tester->execute([]);

        return $tester;
    }
}
