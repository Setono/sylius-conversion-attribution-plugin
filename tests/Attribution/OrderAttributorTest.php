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
        // the customer returning from the payment gateway after completing the order, and a later page view
        $this->track('+5 minutes', 'paypal.com');
        $this->track('+3 days', 'newsletter');
        // another visitor
        $this->track('-10 minutes', 'bing', 'someone-else');
        $order = self::order('client', $this->completedAt);

        $attribution = $this->attribute($order);

        self::assertSame($order, $attribution->getOrder());
        self::assertTrue($attribution->hasPageView());
        self::assertFalse($attribution->isDirect());
        self::assertSame('facebook', $attribution->getSource());
        self::assertSame('cpc', $attribution->getMedium());
        self::assertSame('fall', $attribution->getCampaign());
        self::assertSame('https://www.facebook.com/', $attribution->getReferrer());
        self::assertSame('https://shop.example/products/shoes', $attribution->getLandingPage());
        self::assertEquals($this->completedAt->modify('-1 hour'), $attribution->getTrackedAt());
    }

    /**
     * @test
     */
    public function it_credits_the_newest_page_view_even_if_it_was_stored_first(): void
    {
        $this->track('-1 hour', 'facebook');
        $this->track('-2 hours', 'google');

        self::assertSame('facebook', $this->attribute(self::order('client', $this->completedAt))->getSource());
    }

    /**
     * @test
     */
    public function it_attributes_an_order_without_a_non_direct_page_view_to_the_newest_direct_one(): void
    {
        $this->track('-2 days', 'direct', details: ['page' => 'https://shop.example/']);
        $this->track('-1 hour', 'direct', details: ['page' => 'https://shop.example/products/shoes']);

        $attribution = $this->attribute(self::order('client', $this->completedAt));

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

        self::assertSame('facebook', $this->attribute(self::order('client', $this->completedAt))->getSource());
    }

    /**
     * @test
     */
    public function it_credits_page_views_back_to_the_start_of_the_lookback_window(): void
    {
        $this->track('-30 days -1 second', 'google');
        $this->track('-30 days', 'facebook');
        $this->track('-1 hour', 'direct');

        self::assertSame('facebook', $this->attribute(self::order('client', $this->completedAt))->getSource());
    }

    /**
     * @test
     */
    public function it_credits_an_order_without_a_page_view_in_the_lookback_window_to_nothing(): void
    {
        $this->track('-30 days -1 second', 'google');
        $this->track('-1 hour', 'facebook', 'someone-else');
        $order = self::order('client', $this->completedAt);

        $attribution = $this->attribute($order);

        self::assertSame($order, $attribution->getOrder());
        self::assertFalse($attribution->hasPageView());
        self::assertFalse($attribution->isDirect());
        self::assertNull($attribution->getSource());
        self::assertNull($attribution->getLandingPage());
    }

    /**
     * @test
     */
    public function it_credits_page_views_tracked_for_the_customers_other_client_ids(): void
    {
        $this->track('-2 days', 'google');
        $this->track('-1 day', 'newsletter', 'phone', ['medium' => 'email']);
        $this->track('-1 hour', 'direct');
        $order = self::order('client', $this->completedAt, self::customer('laptop', 'phone', 'client'));

        $attribution = $this->attribute($order);

        self::assertSame('newsletter', $attribution->getSource());
        self::assertSame('email', $attribution->getMedium());
    }

    /**
     * @test
     */
    public function it_credits_page_views_tracked_for_the_orders_client_id_even_if_the_customer_lacks_it(): void
    {
        $this->track('-2 days', 'newsletter', 'phone');
        $this->track('-1 day', 'google');
        $order = self::order('client', $this->completedAt, self::customer('phone'));

        self::assertSame('google', $this->attribute($order)->getSource());
    }

    /**
     * @test
     */
    public function it_refuses_an_order_that_is_not_completed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->attribute(self::order('client', null));
    }

    /**
     * @test
     */
    public function it_refuses_an_order_without_a_client_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->attribute(self::order(null, $this->completedAt));
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

    private function attribute(OrderInterface $order): AttributionInterface
    {
        $attributor = self::getContainer()->get(OrderAttributorInterface::class);
        self::assertInstanceOf(OrderAttributorInterface::class, $attributor);

        return $attributor->attribute($order);
    }
}
