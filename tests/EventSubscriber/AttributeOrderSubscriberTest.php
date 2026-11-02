<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributorInterface;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AttributeOrderSubscriber;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Customer;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Order;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Customer as BaseCustomer;
use Sylius\Component\Core\Model\Order as BaseOrder;

final class AttributeOrderSubscriberTest extends TestCase
{
    /**
     * @test
     */
    public function it_subscribes_to_the_completion_of_an_order(): void
    {
        self::assertSame(['sylius.order.pre_complete' => 'attribute'], AttributeOrderSubscriber::getSubscribedEvents());
    }

    /**
     * @test
     */
    public function it_adds_the_client_id_to_the_order_and_the_customer(): void
    {
        $customer = new Customer();
        $customer->addClientId('earlier-client');

        $order = new Order();
        $order->setCustomer($customer);

        $this->createSubscriber('client')->attribute(new ResourceControllerEvent($order));

        self::assertSame('client', $order->getClientId());
        self::assertSame(['earlier-client', 'client'], $customer->getClientIds());
    }

    /**
     * @test
     */
    public function it_attributes_the_order_once_it_has_the_client_id(): void
    {
        $order = new Order();

        $orderAttributor = $this->createMock(OrderAttributorInterface::class);
        $orderAttributor->expects(self::once())->method('attribute')->willReturnCallback(
            static function (OrderInterface $attributed) use ($order): ?AttributionInterface {
                self::assertSame($order, $attributed);
                self::assertSame('client', $attributed->getClientId());

                return null;
            },
        );

        $this->createSubscriber('client', $orderAttributor)->attribute(new ResourceControllerEvent($order));
    }

    /**
     * @test
     */
    public function it_adds_the_client_id_to_an_order_without_a_customer(): void
    {
        $order = new Order();

        $this->createSubscriber('client')->attribute(new ResourceControllerEvent($order));

        self::assertSame('client', $order->getClientId());
    }

    /**
     * @test
     */
    public function it_skips_a_customer_that_does_not_implement_the_plugin_interface(): void
    {
        $order = new Order();
        $order->setCustomer(new BaseCustomer());

        $this->createSubscriber('client')->attribute(new ResourceControllerEvent($order));

        self::assertSame('client', $order->getClientId());
    }

    /**
     * @test
     */
    public function it_skips_an_order_that_does_not_implement_the_plugin_interface(): void
    {
        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->expects(self::never())->method('getClient');

        $orderAttributor = $this->createMock(OrderAttributorInterface::class);
        $orderAttributor->expects(self::never())->method('attribute');

        (new AttributeOrderSubscriber($clientContext, $orderAttributor))->attribute(new ResourceControllerEvent(new BaseOrder()));
    }

    private function createSubscriber(string $clientId, ?OrderAttributorInterface $orderAttributor = null): AttributeOrderSubscriber
    {
        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->method('getClient')->willReturn(new Client($clientId));

        return new AttributeOrderSubscriber($clientContext, $orderAttributor ?? $this->createMock(OrderAttributorInterface::class));
    }
}
