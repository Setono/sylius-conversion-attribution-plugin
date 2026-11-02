<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Attribution;

use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributorInterface;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs against the test application with its default lookback window of 30 days
 */
final class OrderAttributorTest extends KernelTestCase
{
    use PageViewsAndOrders;

    private \DateTimeImmutable $completedAt;

    protected function setUp(): void
    {
        $this->completedAt = new \DateTimeImmutable('2026-10-26 12:00:00');
    }

    /**
     * @test
     */
    public function it_attributes_an_order_to_the_newest_non_direct_page_view_before_checkout(): void
    {
        $this->track('-2 hours', 'google');
        $this->track('-1 hour', 'facebook', details: [
            'medium' => 'cpc',
            'campaign' => 'fall',
            'referrer' => 'https://www.facebook.com/',
            'page' => 'https://shop.example/products/shoes',
        ]);
        $this->track('-30 minutes', 'direct');
        // the customer returning from the payment gateway after completing the order, and a later visit
        $this->track('+5 minutes', 'paypal.com');
        $this->track('+3 days', 'newsletter');
        // another visitor
        $this->track('-10 minutes', 'bing', 'someone-else');
        $order = self::order('client', $this->completedAt);

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
    public function it_persists_the_attribution(): void
    {
        $this->track('-1 hour', 'google');
        $order = self::order('client', $this->completedAt);

        $this->attribute($order);
        self::entityManager()->flush();
        self::entityManager()->clear();

        $stored = self::attributionsOf($order);
        self::assertCount(1, $stored);
        self::assertSame('google', $stored[0]->getSource());
    }

    /**
     * @test
     */
    public function it_credits_the_newest_page_view_even_if_it_was_stored_first(): void
    {
        $this->track('-1 hour', 'facebook');
        $this->track('-2 hours', 'google');

        self::assertSame('facebook', $this->attribute(self::order('client', $this->completedAt))?->getSource());
    }

    /**
     * @test
     */
    public function it_attributes_an_order_without_a_non_direct_page_view_to_the_newest_direct_one(): void
    {
        $this->track('-2 days', 'direct', details: ['page' => 'https://shop.example/']);
        $this->track('-1 hour', 'direct', details: ['page' => 'https://shop.example/products/shoes']);

        $attribution = $this->attribute(self::order('client', $this->completedAt));

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

        self::assertSame('facebook', $this->attribute(self::order('client', $this->completedAt))?->getSource());
    }

    /**
     * @test
     */
    public function it_credits_page_views_back_to_the_start_of_the_lookback_window(): void
    {
        $this->track('-30 days -1 second', 'google');
        $this->track('-30 days', 'facebook');
        $this->track('-1 hour', 'direct');

        self::assertSame('facebook', $this->attribute(self::order('client', $this->completedAt))?->getSource());
    }

    /**
     * @test
     */
    public function it_attributes_an_order_being_completed_as_of_now(): void
    {
        self::trackPageView('client', 'google', new \DateTimeImmutable('-30 days -1 hour'));
        self::trackPageView('client', 'facebook', new \DateTimeImmutable('-1 hour'));

        self::assertSame('facebook', $this->attribute(self::order('client', null))?->getSource());
    }

    /**
     * @test
     */
    public function it_does_not_attribute_an_order_without_a_page_view_in_the_lookback_window(): void
    {
        $this->track('-30 days -1 second', 'google');
        $this->track('-1 hour', 'facebook', 'someone-else');
        $order = self::order('client', $this->completedAt);

        self::assertNull($this->attribute($order));
        self::entityManager()->flush();
        self::assertSame([], self::attributionsOf($order));
    }

    /**
     * @test
     */
    public function it_does_not_attribute_an_order_without_a_client_id(): void
    {
        $this->track('-1 hour', 'google');

        self::assertNull($this->attribute(self::order(null, $this->completedAt)));
    }

    protected static function container(): ContainerInterface
    {
        return self::getContainer();
    }

    /**
     * @param array{medium?: string, campaign?: string, referrer?: string, page?: string} $details
     */
    private function track(string $relativeToCheckout, string $source, string $clientId = 'client', array $details = []): void
    {
        $trackedAt = $this->completedAt->modify($relativeToCheckout);
        self::assertNotFalse($trackedAt);

        self::trackPageView($clientId, $source, $trackedAt, $details);
    }

    private function attribute(OrderInterface $order): ?AttributionInterface
    {
        $attributor = self::getContainer()->get(OrderAttributorInterface::class);
        self::assertInstanceOf(OrderAttributorInterface::class, $attributor);

        return $attributor->attribute($order);
    }
}
