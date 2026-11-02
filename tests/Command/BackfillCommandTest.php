<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributorInterface;
use Setono\SyliusConversionAttributionPlugin\Command\BackfillCommand;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
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
        // after the checkout, so it must not take the credit
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
        // stays without an attribution, so the next batch must not start over with it
        self::order('without-page-views', new \DateTimeImmutable('2026-10-01 12:00:00'));
        $third = self::order('third', new \DateTimeImmutable('2026-10-01 12:00:00'));
        self::trackPageView('third', 'facebook', new \DateTimeImmutable('2026-09-30 12:00:00'));

        $tester = $this->execute(batchSize: 2);

        $tester->assertCommandIsSuccessful();
        // each batch is flushed and cleared, so a large backfill doesn't hold every order in memory
        self::assertSame(0, self::entityManager()->getUnitOfWork()->size());
        self::assertSame(['google'], $this->attributedSources($first));
        self::assertSame(['facebook'], $this->attributedSources($third));
        // the progress bar is finished, followed by a blank line and the result
        self::assertMatchesRegularExpression('# 3/3 \[.+\] 100%\n\n \[OK\] Attributed 2 of the 3 orders without an attribution#u', $tester->getDisplay());
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

    /**
     * Runs the command as the application registers it or, given a batch size, as built with it
     */
    private function execute(?int $batchSize = null): CommandTester
    {
        $name = 'setono:sylius-conversion-attribution:backfill';

        if (null === $batchSize) {
            // getContainer() boots the kernel, unless the test already did
            self::getContainer();
            $command = (new Application(self::$kernel))->find($name);
        } else {
            $container = self::getContainer();
            $orderAttributor = $container->get(OrderAttributorInterface::class);
            self::assertInstanceOf(OrderAttributorInterface::class, $orderAttributor);
            $orderClass = self::modelClass('sylius.model.order.class', OrderInterface::class);
            $attributionClass = self::modelClass('setono_sylius_conversion_attribution.model.attribution.class', AttributionInterface::class);

            $doctrine = $container->get('doctrine');
            self::assertInstanceOf(ManagerRegistry::class, $doctrine);

            $application = new ConsoleApplication();
            $application->add(new BackfillCommand($doctrine, $orderAttributor, $orderClass, $attributionClass, $batchSize));
            $command = $application->find($name);
        }

        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }
}
