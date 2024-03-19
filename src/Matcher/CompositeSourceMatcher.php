<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Setono\CompositeCompilerPass\CompositeService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends CompositeService<SourceMatcherInterface>
 */
final class CompositeSourceMatcher extends CompositeService implements SourceMatcherInterface
{
    public function match(Request $request): ?Source
    {
        foreach ($this->services as $service) {
            $source = $service->match($request);

            if (null !== $source) {
                return $source;
            }
        }

        return null;
    }
}
