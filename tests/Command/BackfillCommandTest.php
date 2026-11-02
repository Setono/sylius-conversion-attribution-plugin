<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributor;
use Setono\SyliusConversionAttributionPlugin\Command\BackfillCommand;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\Order;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\StoredAttribution;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\StoredAttributionFactory;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\TrackedSource;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\InMemoryEntityManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class BackfillCommandTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = InMemoryEntityManager::create();
    }

    /**
     * @test
     */
    public function it_attributes_completed_orders_as_of_their_checkout(): void
    {
        $order = $this->order('client', '2026-10-01 12:00:00');
        $this->track('client', 'google', '2026-09-30 12:00:00');
        // after the checkout, so it must not take the credit
        $this->track('client', 'facebook', '2026-10-05 12:00:00');

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
        $attributed = $this->order('attributed', '2026-10-01 12:00:00');
        $this->track('attributed', 'google', '2026-09-30 12:00:00');
        $existing = new StoredAttribution();
        $existing->setOrder($attributed);
        $existing->setSource('newsletter');
        $existing->setTrackedAt(new \DateTimeImmutable('2026-09-29 12:00:00'));
        $this->em->persist($existing);

        $untracked = $this->order(null, '2026-10-01 12:00:00');

        $inCheckout = $this->order('in-checkout', '2026-10-01 12:00:00');
        $inCheckout->setCheckoutCompletedAt(null);
        $this->track('in-checkout', 'google', '2026-09-30 12:00:00');

        $withoutPageViews = $this->order('without-page-views', '2026-10-01 12:00:00');
        $this->em->flush();

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
        $first = $this->order('first', '2026-10-01 12:00:00');
        $this->track('first', 'google', '2026-09-30 12:00:00');
        // stays without an attribution, so the next batch must not start over with it
        $this->order('without-page-views', '2026-10-01 12:00:00');
        $third = $this->order('third', '2026-10-01 12:00:00');
        $this->track('third', 'facebook', '2026-09-30 12:00:00');

        $tester = $this->execute(batchSize: 2);

        $tester->assertCommandIsSuccessful();
        // each batch is flushed and cleared, so a large backfill doesn't hold every order in memory
        self::assertSame(0, $this->em->getUnitOfWork()->size());
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

    private function order(?string $clientId, string $checkoutCompletedAt): Order
    {
        $order = Order::completed($clientId, new \DateTimeImmutable($checkoutCompletedAt));
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    private function track(string $clientId, string $source, string $createdAt): void
    {
        $this->em->persist(TrackedSource::at(new \DateTimeImmutable($createdAt), $clientId, $source));
        $this->em->flush();
    }

    /**
     * @return list<string|null>
     */
    private function attributedSources(Order $order): array
    {
        $this->em->clear();

        return array_map(
            static fn (StoredAttribution $attribution): ?string => $attribution->getSource(),
            $this->em->getRepository(StoredAttribution::class)->findBy(['order' => $order->getId()]),
        );
    }

    private function execute(int $batchSize = 100): CommandTester
    {
        $this->em->clear();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        $orderAttributor = new OrderAttributor($registry, new StoredAttributionFactory(), TrackedSource::class, 30);

        // Look the command up by its name, like the console does, so the name is tested too
        $application = new Application();
        $application->add(new BackfillCommand($registry, $orderAttributor, Order::class, StoredAttribution::class, $batchSize));

        $tester = new CommandTester($application->find('setono:sylius-conversion-attribution:backfill'));
        $tester->execute([]);

        return $tester;
    }
}
