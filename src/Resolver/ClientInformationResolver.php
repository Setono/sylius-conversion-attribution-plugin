<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Resolver;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ClientInformationResolver implements ClientInformationResolverInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClientContextInterface $clientContext,
        private readonly ReferrerParserInterface $referrerParser,
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

        $source = $this->sourceMatcher->match($request);
        if (null !== $source) {
            $clientInformation->source = $source->source;
            $clientInformation->medium = $source->medium;
            $clientInformation->campaign = $source->campaign;
        }

        $referrer = $request->headers->get('referer');
        $clientInformation->referrer = $referrer;

        if (null !== $referrer && null === $clientInformation->source && null === $clientInformation->medium) {
            $parsedReferrer = $this->referrerParser->parse($referrer);
            $clientInformation->source = $parsedReferrer->source;
            $clientInformation->medium = $parsedReferrer->medium;
        }

        if (null === $clientInformation->source) {
            $clientInformation->source = $this->defaultSource;
        }

        return $clientInformation;
    }
}
