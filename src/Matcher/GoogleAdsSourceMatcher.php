<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Symfony\Component\HttpFoundation\Request;

final class GoogleAdsSourceMatcher implements SourceMatcherInterface
{
    public function __construct(
        private readonly string $source = 'google',
        private readonly string $medium = 'cpc',
    ) {
    }

    public function match(Request $request): ?Source
    {
        foreach (['gclid', 'gbraid', 'wbraid'] as $parameter) {
            if ($request->query->has($parameter)) {
                return new Source($this->source, $this->medium);
            }
        }

        return null;
    }
}
