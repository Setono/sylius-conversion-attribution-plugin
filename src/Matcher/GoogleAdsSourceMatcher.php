<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Symfony\Component\HttpFoundation\Request;

final class GoogleAdsSourceMatcher implements SourceMatcherInterface
{
    public function match(Request $request): ?Source
    {
        foreach (['gclid', 'gbraid', 'wbraid'] as $parameter) {
            if ($request->query->has($parameter)) {
                return new Source('google', 'cpc');
            }
        }

        return null;
    }
}
