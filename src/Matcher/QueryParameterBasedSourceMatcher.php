<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Symfony\Component\HttpFoundation\Request;

final class QueryParameterBasedSourceMatcher implements SourceMatcherInterface
{
    public function __construct(
        /**
         * @var list<string> $parameters
         */
        public readonly array $parameters,
    ) {
    }

    public function match(Request $request): ?Source
    {
        foreach ($this->parameters as $parameter) {
            $source = $request->query->get($parameter);
            if (is_string($source) && '' !== $source) {
                return new Source($source);
            }
        }

        return null;
    }
}
