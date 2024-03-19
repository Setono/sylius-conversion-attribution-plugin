<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Symfony\Component\HttpFoundation\Request;

interface SourceMatcherInterface
{
    /**
     * Tries to match the request query to a source and returns a Source if it matches or null if it doesn't
     */
    public function match(Request $request): ?Source;
}
