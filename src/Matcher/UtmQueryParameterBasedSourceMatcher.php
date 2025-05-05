<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Symfony\Component\HttpFoundation\Request;

final class UtmQueryParameterBasedSourceMatcher implements SourceMatcherInterface
{
    public function match(Request $request): ?Source
    {
        $source = $request->query->get('utm_source');
        $medium = $request->query->get('utm_medium');
        $campaign = $request->query->get('utm_campaign');

        if (is_string($source)) {
            return new Source(
                $source,
                is_string($medium) ? $medium : null,
                is_string($campaign) ? $campaign : null,
            );
        }

        return null;
    }
}
