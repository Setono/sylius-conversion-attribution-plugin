<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\EventSubscriber;

use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolverInterface;
use Setono\TagBag\Tag\InlineScriptTag;
use Setono\TagBag\TagBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AddJavascriptSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TagBagInterface $tagBag,
        private readonly ClientInformationResolverInterface $clientInformationResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BotDetectorInterface $botDetector,
        private readonly CookieProviderInterface $cookieProvider,
        private readonly int $sessionTimeout,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'addJavascript',
        ];
    }

    public function addJavascript(RequestEvent $event): void
    {
        if (!$event->isMainRequest() ||
            $event->getRequest()->isXmlHttpRequest() ||
            !$event->getRequest()->isMethod('GET') ||
            !str_contains((string) $event->getRequest()->getRequestFormat(), 'html') ||
            $this->botDetector->isBotRequest($event->getRequest())
        ) {
            return;
        }

        $cookie = $this->cookieProvider->getCookie();
        if (null !== $cookie && $cookie->lastSeenAt >= (time() - $this->sessionTimeout)) {
            return;
        }

        $clientInformation = $this->clientInformationResolver->resolve($event->getRequest());
        $javascript = <<<'JS'
fetch('%s', {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: '%s'
});
JS;

        try {
            $javascript = sprintf(
                $javascript,
                $this->urlGenerator->generate('setono_sylius_conversion_attribution_shop_track'),
                json_encode($clientInformation, \JSON_THROW_ON_ERROR),
            );
        } catch (\JsonException) {
            return;
        }

        $this->tagBag->add(InlineScriptTag::create($javascript));
    }
}
