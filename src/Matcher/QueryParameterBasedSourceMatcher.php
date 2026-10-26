<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

final class QueryParameterBasedSourceMatcher implements SourceMatcherInterface
{
    public function __construct(
        /** @var list<array{matches: list<string>, source: string|null, medium: string|null, campaign: string|null}> $parameters */
        private readonly array $parameters,
    ) {
    }

    public function match(PageView $pageView): ?Source
    {
        foreach ($this->parameters as $parameter) {
            foreach ($parameter['matches'] as $match) {
                $source = $pageView->getQueryParameter($match);
                if (null === $source || '' === $source) {
                    continue;
                }

                return new Source($parameter['source'] ?? $source, $parameter['medium'], $parameter['campaign']);
            }
        }

        return null;
    }
}
