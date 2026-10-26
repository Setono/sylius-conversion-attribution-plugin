<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Setono\CompositeCompilerPass\CompositeService;

/**
 * @extends CompositeService<SourceMatcherInterface>
 */
final class CompositeSourceMatcher extends CompositeService implements SourceMatcherInterface
{
    public function match(PageView $pageView): ?Source
    {
        foreach ($this->services as $service) {
            $source = $service->match($pageView);

            if (null !== $source) {
                return $source;
            }
        }

        return null;
    }
}
