<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddClientIdSubscriber;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Customer;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Order;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Customer as BaseCustomer;
use Sylius\Component\Core\Model\Order as BaseOrder;

final class AddClientIdSubscriberTest extends TestCase
{
    /**
     * @test
     */
    public function it_subscribes_to_the_completion_of_an_order(): void
    {
        self::assertSame(['sylius.order.pre_complete' => 'add'], AddClientIdSubscriber::getSubscribedEvents());
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

        $this->createSubscriber('client')->add(new ResourceControllerEvent($order));

        self::assertSame('client', $order->getClientId());
        self::assertSame(['earlier-client', 'client'], $customer->getClientIds());
    }

    /**
     * @test
     */
    public function it_adds_the_client_id_to_an_order_without_a_customer(): void
    {
        $order = new Order();

        $this->createSubscriber('client')->add(new ResourceControllerEvent($order));

        self::assertSame('client', $order->getClientId());
    }

    /**
     * @test
     */
    public function it_skips_a_customer_that_does_not_implement_the_plugin_interface(): void
    {
        $order = new Order();
        $order->setCustomer(new BaseCustomer());

        $this->createSubscriber('client')->add(new ResourceControllerEvent($order));

        self::assertSame('client', $order->getClientId());
    }

    /**
     * @test
     */
    public function it_skips_an_order_that_does_not_implement_the_plugin_interface(): void
    {
        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->expects(self::never())->method('getClient');

        (new AddClientIdSubscriber($clientContext))->add(new ResourceControllerEvent(new BaseOrder()));
    }

    private function createSubscriber(string $clientId): AddClientIdSubscriber
    {
        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->method('getClient')->willReturn(new Client($clientId));

        return new AddClientIdSubscriber($clientContext);
    }
}
