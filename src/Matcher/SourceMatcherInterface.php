<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

interface SourceMatcherInterface
{
    /**
     * Tries to match the page view to a source and returns a Source if it matches or null if it doesn't
     */
    public function match(PageView $pageView): ?Source;
}
