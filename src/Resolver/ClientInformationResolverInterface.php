<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Resolver;

use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Symfony\Component\HttpFoundation\Request;

interface ClientInformationResolverInterface
{
    /**
     * Resolves client information for the page view described by the given request
     */
    public function resolve(Request $request): ClientInformation;
}
