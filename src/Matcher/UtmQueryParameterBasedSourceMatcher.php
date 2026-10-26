<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

final class UtmQueryParameterBasedSourceMatcher implements SourceMatcherInterface
{
    public function match(PageView $pageView): ?Source
    {
        $source = $pageView->getQueryParameter('utm_source');
        if (null === $source) {
            return null;
        }

        return new Source($source, $pageView->getQueryParameter('utm_medium'), $pageView->getQueryParameter('utm_campaign'));
    }
}
