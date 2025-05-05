<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Setono\SyliusConversionAttributionPlugin\Parser\Referrer;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParserInterface;
use Symfony\Component\HttpFoundation\Request;

final class ReferrerBasedSourceMatcher implements SourceMatcherInterface
{
    public function __construct(private readonly ReferrerParserInterface $referrerParser)
    {
    }

    public function match(Request $request): ?Source
    {
        $referrer = $request->headers->get('referer');
        if (!is_string($referrer) || '' === $referrer) {
            return null;
        }

        $parsedReferrer = $this->referrerParser->parse($referrer);
        if (null !== $parsedReferrer->source && !in_array($parsedReferrer->medium, [Referrer::MEDIUM_INVALID, Referrer::MEDIUM_UNKNOWN], true)) {
            return new Source($parsedReferrer->source, $parsedReferrer->medium);
        }

        $host = parse_url($referrer, \PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return null;
        }

        return new Source($host, 'referral');
    }
}
