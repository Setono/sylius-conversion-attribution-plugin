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
        // @deprecated unused since 1.2, kept for signature compatibility — remove in 2.0
        private readonly ClientInformationResolverInterface $clientInformationResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        // @deprecated unused since 1.2, kept for signature compatibility — remove in 2.0
        private readonly BotDetectorInterface $botDetector,
        // @deprecated unused since 1.2, kept for signature compatibility — remove in 2.0
        private readonly CookieProviderInterface $cookieProvider,
        private readonly int $sessionTimeout,
        // @deprecated unused since 1.2, kept for signature compatibility — remove in 2.0
        private readonly ?SourceMatcherInterface $sourceMatcher = null,
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

        // Only conditions that are part of a full page cache's key (method, path, format) may
        // influence whether the snippet is injected: the rendered HTML is shared between visitors,
        // so any per-visitor decision (bot detection, session freshness) would be frozen into the
        // cache by whichever visitor populated it. Per-visitor logic lives in the snippet itself
        // (session throttle) or in TrackAction at POST time (bot filtering, client id, matching).
        if (!$event->isMainRequest() ||
            $request->isXmlHttpRequest() ||
            !$request->isMethod('GET') ||
            !str_contains((string) $request->getRequestFormat(), 'html')
        ) {
            return;
        }

        // %d = session timeout in milliseconds, %s = JSON-encoded track URL.
        // NOTE: this is a sprintf template — a literal % in the JS must be written as %%.
        // The snippet is deliberately identical for every visitor so full page caches can safely
        // store it: the client id rides the setono_client_id cookie on the fetch() POST, and the
        // session throttle is evaluated in the browser against a rolling localStorage timestamp.
        // A query string or a cross-host referrer (campaign hints) bypasses the throttle; the
        // server side source matcher makes the definitive call at POST time.
        $javascript = <<<'JS'
(function () {
    var now = Date.now();
    var last = null;

    try {
        last = parseInt(window.localStorage.getItem('setono_conversion_attribution_last_seen'), 10);
        window.localStorage.setItem('setono_conversion_attribution_last_seen', String(now));
    } catch (e) {
        // storage unavailable (private mode, storage disabled): track every page view
    }

    var campaignHint = window.location.search !== '';
    if (!campaignHint && document.referrer !== '') {
        try {
            campaignHint = new URL(document.referrer).host !== window.location.host;
        } catch (e) {
            campaignHint = true;
        }
    }

    if (!campaignHint && last !== null && !isNaN(last) && now - last < %d) {
        return;
    }

    var payload = {page: window.location.href};
    if (document.referrer !== '') {
        payload.referrer = document.referrer;
    }

    fetch(%s, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
})();
JS;

        try {
            $this->tagBag->add(InlineScriptTag::create(sprintf(
                $javascript,
                $this->sessionTimeout * 1000,
                // The URL is interpolated raw into an inline <script>, so the encoding must
                // neutralize ', ", <, >, & to prevent breaking out of the JS string or the script
                // tag. json_encode() emits the surrounding double quotes itself.
                json_encode(
                    $this->urlGenerator->generate('setono_sylius_conversion_attribution_global_track'),
                    \JSON_THROW_ON_ERROR | \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP,
                ),
            )));
        } catch (\JsonException) {
        }
    }
}
