<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\EventSubscriber;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributorInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * When an order is completed, stamps the visitor's client id on it (and its customer) and records its
 * attribution, which is flushed with the order
 */
final class AttributeOrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientContextInterface $clientContext,
        private readonly OrderAttributorInterface $orderAttributor,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.order.pre_complete' => 'attribute',
        ];
    }

    public function attribute(ResourceControllerEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $clientId = $this->clientContext->getClient()->id;

        $order->setClientId($clientId);

        $customer = $order->getCustomer();
        if ($customer instanceof CustomerInterface) {
            $customer->addClientId($clientId);
        }

        $this->orderAttributor->attribute($order);
    }
}
