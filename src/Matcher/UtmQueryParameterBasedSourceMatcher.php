<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;

/**
 * Not a matcher in the chain: it applies the UTM parameters on top of what the chain matched, so each one
 * that is set wins over the same field of the chain's match. See
 * docs/adr/0001-utm-parameters-overlay-the-matcher-chain.md
 */
final class UtmQueryParameterBasedSourceMatcher implements SourceMatcherInterface
{
    public function __construct(private readonly SourceMatcherInterface $matcher)
    {
    }

    public function match(PageView $pageView): ?Source
    {
        $match = $this->matcher->match($pageView);

        $utmSource = $pageView->getQueryParameter('utm_source');
        $utmMedium = $pageView->getQueryParameter('utm_medium');
        $utmCampaign = $pageView->getQueryParameter('utm_campaign');

        if (null === $utmSource && null === $utmMedium && null === $utmCampaign) {
            return $match;
        }

        // The medium and campaign of another source don't apply to this one
        if (null !== $utmSource && null !== $match && 0 !== strcasecmp($utmSource, $match->source)) {
            $match = null;
        }

        return new Source(
            $utmSource ?? $match->source ?? SourceInterface::NOT_SET,
            $utmMedium ?? $match?->medium,
            $utmCampaign ?? $match?->campaign,
        );
    }
}
