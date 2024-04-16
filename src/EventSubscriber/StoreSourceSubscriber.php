<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreSourceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly CookieProviderInterface $cookieProvider,
        private readonly SourceFactoryInterface $sourceFactory,
        private readonly int $sessionTimeout,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'store',
        ];
    }

    public function store(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getRequest()->isXmlHttpRequest()) {
            return;
        }

        $cookie = $this->cookieProvider->getCookie();
        if (null !== $cookie && $cookie->lastSeenAt >= (time() - $this->sessionTimeout)) {
            return;
        }

        $source = $this->sourceFactory->createFromRequest($event->getRequest());

        $manager = $this->managerRegistry->getManagerForClass($source::class);
        $manager?->persist($source);
        $manager?->flush();
    }
}
