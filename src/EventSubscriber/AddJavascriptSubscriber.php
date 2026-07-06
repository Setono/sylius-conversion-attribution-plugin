<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\EventSubscriber;

use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
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
        private readonly SourceMatcherInterface $sourceMatcher,
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
        $request = $event->getRequest();

        if (!$event->isMainRequest() ||
            $request->isXmlHttpRequest() ||
            !$request->isMethod('GET') ||
            !str_contains((string) $request->getRequestFormat(), 'html') ||
            $this->botDetector->isBotRequest($request)
        ) {
            return;
        }

        // A request that carries campaign markers (utm/click id/cross-host referrer) is always
        // tracked, even mid-session, so a paid click arriving after an organic entry isn't lost
        $hasCampaign = null !== $this->sourceMatcher->match($request);

        if (!$hasCampaign) {
            $clientCookie = $this->cookieProvider->getCookie();
            if (null !== $clientCookie && $clientCookie->lastSeenAt >= (time() - $this->sessionTimeout)) {
                return;
            }
        }

        $clientInformation = $this->clientInformationResolver->resolve($request);
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
                $this->urlGenerator->generate('setono_sylius_conversion_attribution_global_track'),
                // The payload is interpolated raw into an inline <script>, so the encoding must
                // neutralize ', ", <, >, & to prevent breaking out of the JS string or the script tag
                json_encode(
                    $clientInformation,
                    \JSON_THROW_ON_ERROR | \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP,
                ),
            );

            $this->tagBag->add(InlineScriptTag::create($javascript));
        } catch (\JsonException) {
        }
    }
}
