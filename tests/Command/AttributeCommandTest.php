<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Setono\SyliusConversionAttributionPlugin\Command\AttributeCommand;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Lock\LockFactory;

final class AttributeCommandTest extends KernelTestCase
{
    use PageViewsAndOrders;

    /**
     * @test
     */
    public function it_attributes_completed_orders_as_of_their_checkout(): void
    {
        $order = self::order('client', new \DateTimeImmutable('-2 days'));
        self::trackPageView('client', 'google', new \DateTimeImmutable('-3 days'));
        // after the checkout, so the order must not be credited to it
        self::trackPageView('client', 'facebook', new \DateTimeImmutable('-1 day'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        $attributions = $this->storedAttributionsOf($order);
        self::assertCount(1, $attributions);
        self::assertNotNull($attributions[0]->getId());
        self::assertSame('google', $attributions[0]->getSource());
        self::assertStringContainsString('[OK] Orders attributed: 1', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_credits_an_order_without_page_views_to_nothing_once(): void
    {
        $order = self::order('client', new \DateTimeImmutable('-2 days'));

        $this->execute()->assertCommandIsSuccessful();
        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        $attributions = $this->storedAttributionsOf($order);
        self::assertCount(1, $attributions);
        self::assertFalse($attributions[0]->hasPageView());
        self::assertStringContainsString('[OK] No orders to attribute', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_skips_orders_that_cannot_or_need_not_be_attributed(): void
    {
        $attributed = self::order('attributed', new \DateTimeImmutable('-2 days'));
        self::trackPageView('attributed', 'google', new \DateTimeImmutable('-3 days'));
        self::attribution($attributed, ['source' => 'newsletter']);

        $untracked = self::order(null, new \DateTimeImmutable('-2 days'));

        $inCheckout = self::order('in-checkout', null);
        self::trackPageView('in-checkout', 'google', new \DateTimeImmutable('-1 hour'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertSame(['newsletter'], array_map(static fn (AttributionInterface $attribution): ?string => $attribution->getSource(), $this->storedAttributionsOf($attributed)));
        self::assertSame([], $this->storedAttributionsOf($untracked));
        self::assertSame([], $this->storedAttributionsOf($inCheckout));
        self::assertStringContainsString('[OK] No orders to attribute', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_attributes_orders_in_batches(): void
    {
        $first = self::order('first', new \DateTimeImmutable('-2 days'));
        self::trackPageView('first', 'google', new \DateTimeImmutable('-3 days'));
        for ($i = 0; $i < 99; ++$i) {
            self::order('without-page-views', new \DateTimeImmutable('-2 days'));
        }
        // the 101st order, so it's in the second batch (of 100)
        $last = self::order('last', new \DateTimeImmutable('-2 days'));
        self::trackPageView('last', 'facebook', new \DateTimeImmutable('-3 days'));

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        // each batch is flushed and cleared, so a large run doesn't hold every order in memory
        self::assertSame(0, self::entityManager()->getUnitOfWork()->size());
        self::assertSame('google', $this->storedAttributionsOf($first)[0]->getSource());
        self::assertSame('facebook', $this->storedAttributionsOf($last)[0]->getSource());
        self::assertStringContainsString('[OK] Orders attributed: 101', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_only_attributes_orders_completed_in_the_lookback_window_unless_told_to_attribute_all(): void
    {
        $old = self::order('old', new \DateTimeImmutable('-31 days'));
        self::trackPageView('old', 'google', new \DateTimeImmutable('-32 days'));

        $this->execute()->assertCommandIsSuccessful();
        self::assertSame([], $this->storedAttributionsOf($old));

        $tester = $this->execute(['--all' => true]);

        $tester->assertCommandIsSuccessful();
        $attributions = $this->storedAttributionsOf($old);
        self::assertCount(1, $attributions);
        self::assertSame('google', $attributions[0]->getSource());
        self::assertStringContainsString('[OK] Orders attributed: 1', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_reports_success_when_there_is_nothing_to_attribute(): void
    {
        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('[OK] No orders to attribute', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_does_nothing_while_another_run_holds_the_lock(): void
    {
        $order = self::order('client', new \DateTimeImmutable('-2 days'));
        $lockFactory = self::getContainer()->get('lock.factory');
        self::assertInstanceOf(LockFactory::class, $lockFactory);
        $lock = $lockFactory->createLock(AttributeCommand::NAME);
        self::assertTrue($lock->acquire());

        try {
            $tester = $this->execute();
        } finally {
            $lock->release();
        }

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('The command is already running in another process', $tester->getDisplay());
        self::assertSame([], $this->storedAttributionsOf($order));
    }

    protected static function container(): ContainerInterface
    {
        return self::getContainer();
    }

    /**
     * @phpstan-impure
     *
     * @return list<AttributionInterface>
     */
    private function storedAttributionsOf(OrderInterface $order): array
    {
        self::entityManager()->clear();

        return self::attributionsOf($order);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(array $input = []): CommandTester
    {
        // getContainer() boots the kernel, unless the test already did
        self::getContainer();

        $tester = new CommandTester((new Application(self::$kernel))->find(AttributeCommand::NAME));
        $tester->execute($input);

        return $tester;
    }
}
