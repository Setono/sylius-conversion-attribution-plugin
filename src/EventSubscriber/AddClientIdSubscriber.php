<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\EventSubscriber;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AddClientIdSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ClientContextInterface $clientContext)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.order.pre_complete' => 'add',
        ];
    }

    public function add(ResourceControllerEvent $event): void
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
    }
}
