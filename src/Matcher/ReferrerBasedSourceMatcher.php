<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Setono\SyliusConversionAttributionPlugin\Referrer\ReferrerDatabaseInterface;

final class ReferrerBasedSourceMatcher implements SourceMatcherInterface
{
    public function __construct(
        private readonly ReferrerDatabaseInterface $referrerDatabase,
        // The medium of a referral from a site the referrer database has no medium for, or doesn't know
        private readonly string $medium = 'referral',
    ) {
    }

    public function match(PageView $pageView): ?Source
    {
        $referrer = $pageView->referrer;
        if (null === $referrer) {
            return null;
        }

        $known = $this->referrerDatabase->lookup($referrer);
        if (null !== $known) {
            return null === $known->medium ? new Source($known->source, $this->medium) : $known;
        }

        $host = $pageView->referrerHost;
        if (null === $host || $pageView->host === $host) {
            return null;
        }

        return new Source($host, $this->medium);
    }
}
