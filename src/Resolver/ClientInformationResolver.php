<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Resolver;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ClientInformationResolver implements ClientInformationResolverInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClientContextInterface $clientContext,
        private readonly SourceMatcherInterface $sourceMatcher,
        private readonly string $defaultSource = 'direct',
    ) {
    }

    public function resolve(Request $request = null): ClientInformation
    {
        $clientInformation = new ClientInformation();

        $request = $request ?? $this->requestStack->getMainRequest();
        if (null === $request) {
            return $clientInformation;
        }

        $clientInformation->clientId = $this->clientContext->getClient()->id;
        $clientInformation->page = $request->getUri();
        $clientInformation->ip = $request->getClientIp();
        $clientInformation->userAgent = $request->headers->get('user-agent');
        $clientInformation->referrer = $request->headers->get('referer');
        $clientInformation->source = $this->defaultSource;

        $source = $this->sourceMatcher->match($request);
        if (null !== $source) {
            $clientInformation->source = $source->source;
            $clientInformation->medium = $source->medium;
            $clientInformation->campaign = $source->campaign;
        }

        return $clientInformation;
    }
}
