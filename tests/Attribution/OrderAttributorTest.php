<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Attribution;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributor;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\Order;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\StoredAttribution;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\StoredAttributionFactory;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture\TrackedSource;
use Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\InMemoryEntityManager;

final class OrderAttributorTest extends TestCase
{
    private EntityManagerInterface $em;

    private \DateTimeImmutable $completedAt;

    protected function setUp(): void
    {
        $this->em = InMemoryEntityManager::create();
        $this->completedAt = new \DateTimeImmutable('2026-10-26 12:00:00');
    }

    /**
     * @test
     */
    public function it_attributes_an_order_to_the_newest_non_direct_page_view_before_checkout(): void
    {
        $this->track('-2 hours', 'google');
        $facebook = $this->track('-1 hour', 'facebook');
        $facebook->setMedium('cpc');
        $facebook->setCampaign('fall');
        $facebook->setReferrer('https://www.facebook.com/');
        $facebook->setPage('https://shop.example/products/shoes');
        $this->track('-30 minutes', 'direct');
        // the customer returning from the payment gateway after completing the order, and a later visit
        $this->track('+5 minutes', 'paypal.com');
        $this->track('+3 days', 'newsletter');
        // another visitor
        $this->track('-10 minutes', 'bing', 'someone-else');
        $order = $this->order('client');

        $attribution = $this->attribute($order);

        self::assertNotNull($attribution);
        self::assertSame($order, $attribution->getOrder());
        self::assertSame('facebook', $attribution->getSource());
        self::assertSame('cpc', $attribution->getMedium());
        self::assertSame('fall', $attribution->getCampaign());
        self::assertSame('https://www.facebook.com/', $attribution->getReferrer());
        self::assertSame('https://shop.example/products/shoes', $attribution->getLandingPage());
        self::assertEquals($this->completedAt->modify('-1 hour'), $attribution->getTrackedAt());
        self::assertFalse($attribution->isDirect());
    }

    /**
     * @test
     */
    public function it_credits_the_newest_page_view_even_if_it_was_stored_first(): void
    {
        $this->track('-1 hour', 'facebook');
        $this->track('-2 hours', 'google');

        self::assertSame('facebook', $this->attribute($this->order('client'))?->getSource());
    }

    /**
     * @test
     */
    public function it_persists_the_attribution(): void
    {
        $this->track('-1 hour', 'google');
        $order = $this->order('client');

        $this->attribute($order);
        $this->em->flush();
        $this->em->clear();

        $stored = $this->em->getRepository(StoredAttribution::class)->findAll();
        self::assertCount(1, $stored);
        self::assertSame('google', $stored[0]->getSource());
        self::assertSame($order->getId(), $stored[0]->getOrder()?->getId());
    }

    /**
     * @test
     */
    public function it_attributes_an_order_without_a_non_direct_page_view_to_the_newest_direct_one(): void
    {
        $this->track('-2 days', 'direct')->setPage('https://shop.example/');
        $this->track('-1 hour', 'direct')->setPage('https://shop.example/products/shoes');

        $attribution = $this->attribute($this->order('client'));

        self::assertNotNull($attribution);
        self::assertTrue($attribution->isDirect());
        self::assertSame('https://shop.example/products/shoes', $attribution->getLandingPage());
    }

    /**
     * @test
     */
    public function it_credits_page_views_up_to_the_moment_of_checkout(): void
    {
        $this->track('-1 hour', 'google');
        $this->track('+0 seconds', 'facebook');

        self::assertSame('facebook', $this->attribute($this->order('client'))?->getSource());
    }

    /**
     * @test
     */
    public function it_credits_page_views_back_to_the_start_of_the_lookback_window(): void
    {
        $this->track('-30 days -1 second', 'google');
        $this->track('-30 days', 'facebook');
        $this->track('-1 hour', 'direct');

        self::assertSame('facebook', $this->attribute($this->order('client'))?->getSource());
    }

    /**
     * @test
     */
    public function it_does_not_attribute_an_order_without_a_page_view_in_the_lookback_window(): void
    {
        $this->track('-30 days -1 second', 'google');
        $this->track('-1 hour', 'facebook', 'someone-else');

        self::assertNull($this->attribute($this->order('client')));
        $this->em->flush();
        self::assertSame([], $this->em->getRepository(StoredAttribution::class)->findAll());
    }

    /**
     * @test
     */
    public function it_attributes_an_order_being_completed_as_of_now(): void
    {
        $this->em->persist(TrackedSource::at(new \DateTimeImmutable('-30 days -1 hour'), source: 'google'));
        $this->em->persist(TrackedSource::at(new \DateTimeImmutable('-1 hour'), source: 'facebook'));
        $order = Order::inCheckout('client');
        $this->em->persist($order);
        $this->em->flush();

        self::assertSame('facebook', $this->attribute($order)?->getSource());
    }

    /**
     * @test
     */
    public function it_does_not_attribute_an_order_without_a_client_id(): void
    {
        $this->track('-1 hour', 'google');

        self::assertNull($this->attribute($this->order(null)));
    }

    private function track(string $relativeToCheckout, string $source, string $clientId = 'client'): TrackedSource
    {
        $createdAt = $this->completedAt->modify($relativeToCheckout);
        self::assertNotFalse($createdAt);

        $trackedSource = TrackedSource::at($createdAt, $clientId, $source);
        $this->em->persist($trackedSource);
        $this->em->flush();

        return $trackedSource;
    }

    private function order(?string $clientId): Order
    {
        $order = Order::completed($clientId, $this->completedAt);
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    private function attribute(Order $order): ?AttributionInterface
    {
        // Changes to tracked sources made after track() must reach the database before the lookup
        $this->em->flush();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        $attributor = new OrderAttributor($registry, new StoredAttributionFactory(), TrackedSource::class, 30);

        return $attributor->attribute($order);
    }
}
