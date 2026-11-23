<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Sylius\Bundle\UserBundle\Event\UserEvent;
use Sylius\Bundle\UserBundle\UserEvents;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

final class AddClientIdOnLoginSubscriber implements EventSubscriberInterface
{
    use ORMTrait;

    public function __construct(
        private readonly ClientContextInterface $clientContext,
        private readonly SectionProviderInterface $sectionProvider,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
            UserEvents::SECURITY_IMPLICIT_LOGIN => 'onImplicitLogin',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $this->addClientIdTo($event->getAuthenticationToken()->getUser());
    }

    public function onImplicitLogin(UserEvent $event): void
    {
        $this->addClientIdTo($event->getUser());
    }

    private function addClientIdTo(?object $user): void
    {
        // A request to the API may come without the browser's client id cookie, and the client context would then make
        // up an id. The admin has its own section too
        if (!$this->sectionProvider->getSection() instanceof ShopSection) {
            return;
        }

        if (!$user instanceof ShopUserInterface) {
            return;
        }

        $customer = $user->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        if (!$customer->addClientId($this->clientContext->getClient()->id)) {
            return;
        }

        // Nothing else saves it for sure: Sylius only saves a login every so often
        $manager = $this->getManager($customer);
        $manager->persist($customer);
        $manager->flush();
    }
}
