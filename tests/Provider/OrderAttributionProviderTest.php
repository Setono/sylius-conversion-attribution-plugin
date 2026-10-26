<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Provider;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Provider\OrderAttributionProvider;
use Setono\SyliusConversionAttributionPlugin\Tests\Provider\Fixture\TrackedSource;

final class OrderAttributionProviderTest extends TestCase
{
    private EntityManagerInterface $em;

    private \DateTimeImmutable $checkoutCompletedAt;

    protected function setUp(): void
    {
        // The plugin's own mapping for the Source mapped superclass, plus a concrete test entity
        $config = ORMSetup::createConfiguration(true);
        $config->setMetadataDriverImpl(new SimplifiedXmlDriver([
            (string) realpath(__DIR__ . '/../../src/Resources/config/doctrine/model') => 'Setono\SyliusConversionAttributionPlugin\Model',
            (string) realpath(__DIR__ . '/Fixture') => 'Setono\SyliusConversionAttributionPlugin\Tests\Provider\Fixture',
        ]));
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);

        $this->em = new EntityManager($connection, $config);
        (new SchemaTool($this->em))->createSchema([$this->em->getClassMetadata(TrackedSource::class)]);

        $this->checkoutCompletedAt = new \DateTimeImmutable('2026-10-26 12:00:00');
    }

    /**
     * @test
     */
    public function it_returns_the_newest_non_direct_source_tracked_up_to_checkout(): void
    {
        $this->track('client', 'google', '-2 hours');
        $facebook = $this->track('client', 'facebook', '-1 hour');
        $this->track('client', 'direct', '-30 minutes');
        // the customer returning from the payment gateway, after the checkout was completed
        $this->track('client', 'paypal.com', '+5 minutes');
        // a later visit from another campaign
        $this->track('client', 'newsletter', '+3 days');
        // another visitor
        $this->track('someone-else', 'bing', '-10 minutes');

        self::assertSame($facebook, $this->provider()->getSource($this->order('client', $this->checkoutCompletedAt))?->getId());
    }

    /**
     * @test
     */
    public function it_includes_a_source_tracked_at_the_moment_of_checkout(): void
    {
        $this->track('client', 'google', '-1 hour');
        $atCheckout = $this->track('client', 'facebook', '+0 seconds');

        self::assertSame($atCheckout, $this->provider()->getSource($this->order('client', $this->checkoutCompletedAt))?->getId());
    }

    /**
     * @test
     */
    public function it_returns_the_newest_non_direct_source_for_an_order_still_in_checkout(): void
    {
        $this->track('client', 'google', '-1 hour');
        $newest = $this->track('client', 'facebook', '+1 hour');
        $this->track('client', 'direct', '+2 hours');

        self::assertSame($newest, $this->provider()->getSource($this->order('client', null))?->getId());
    }

    /**
     * @test
     */
    public function it_returns_null_when_nothing_but_direct_traffic_was_tracked(): void
    {
        $this->track('client', 'direct', '-1 hour');

        self::assertNull($this->provider()->getSource($this->order('client', $this->checkoutCompletedAt)));
    }

    /**
     * @test
     */
    public function it_returns_null_for_an_order_without_a_client_id(): void
    {
        $this->track('client', 'google', '-1 hour');

        self::assertNull($this->provider()->getSource($this->order(null, $this->checkoutCompletedAt)));
    }

    /**
     * Tracks a source relative to the checkout completion and returns its id
     */
    private function track(string $clientId, string $source, string $relativeToCheckout): ?int
    {
        $createdAt = $this->checkoutCompletedAt->modify($relativeToCheckout);
        self::assertNotFalse($createdAt);

        $trackedSource = new TrackedSource($clientId, $source, $createdAt);
        $this->em->persist($trackedSource);
        $this->em->flush();

        return $trackedSource->getId();
    }

    private function order(?string $clientId, ?\DateTimeInterface $checkoutCompletedAt): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getClientId')->willReturn($clientId);
        $order->method('getCheckoutCompletedAt')->willReturn($checkoutCompletedAt);

        return $order;
    }

    private function provider(): OrderAttributionProvider
    {
        $this->em->clear();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        return new OrderAttributionProvider($registry, TrackedSource::class, 'direct');
    }
}
