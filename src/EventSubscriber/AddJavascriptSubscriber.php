<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\EventSubscriber;

use Setono\TagBag\Tag\ScriptTag;
use Setono\TagBag\TagBagInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AddJavascriptSubscriber implements EventSubscriberInterface
{
    /**
     * The tracking script, as installed by assets:install from src/Resources/public
     */
    public const SCRIPT_PATH = 'bundles/setonosyliusconversionattributionplugin/js/track.js';

    public function __construct(
        private readonly TagBagInterface $tagBag,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $packages,
        private readonly int $sessionTimeout,
        /** @var list<string> $campaignParameters the query parameters that make the script skip its session throttle */
        private readonly array $campaignParameters,
        // A hash of the script's contents, so browsers and CDNs fetch the new script after an upgrade
        private readonly string $scriptVersion,
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
        // influence whether the script is injected: the rendered HTML is shared between visitors,
        // so any per-visitor decision (bot detection, session freshness) would be frozen into the
        // cache by whichever visitor populated it. Per-visitor logic lives in the script itself
        // (automation check, session throttle) or in TrackAction at POST time (bot filtering,
        // client id, source matching).
        if (!$event->isMainRequest() ||
            $request->isXmlHttpRequest() ||
            !$request->isMethod('GET') ||
            !str_contains((string) $request->getRequestFormat(), 'html')
        ) {
            return;
        }

        $src = $this->packages->getUrl(self::SCRIPT_PATH);
        $src .= (str_contains($src, '?') ? '&' : '?') . 'v=' . $this->scriptVersion;

        // The tag is identical for every visitor, so full page caches can safely store it. The tag
        // bag renders attribute values raw, hence the escaping
        $this->tagBag->add(
            ScriptTag::create(htmlspecialchars($src))
                ->defer()
                ->withAttribute('data-endpoint', htmlspecialchars($this->urlGenerator->generate('setono_sylius_conversion_attribution_global_track')))
                ->withAttribute('data-session-timeout', $this->sessionTimeout)
                ->withAttribute('data-campaign-parameters', htmlspecialchars(implode(' ', $this->campaignParameters))),
        );
    }
}
