<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Resolver;

use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Symfony\Component\HttpFoundation\Request;

interface ClientInformationResolverInterface
{
    /**
     * Will resolve client information from the request (if set), else it will use the main request
     */
    public function resolve(Request $request = null): ClientInformation;
}
