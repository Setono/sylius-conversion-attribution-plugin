<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Setono\SyliusConversionAttributionPlugin\Parser\Referrer;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParserInterface;

final class ReferrerBasedSourceMatcher implements SourceMatcherInterface
{
    public function __construct(
        private readonly ReferrerParserInterface $referrerParser,
        private readonly string $medium = 'referral',
    ) {
    }

    public function match(PageView $pageView): ?Source
    {
        $referrer = $pageView->referrer;
        if (null === $referrer || '' === $referrer) {
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

        if ($pageView->host === $host) {
            return null;
        }

        return new Source($host, $this->medium);
    }
}
