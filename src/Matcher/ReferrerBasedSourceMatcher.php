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
        if (null === $host || self::withoutWww($pageView->host) === self::withoutWww($host)) {
            return null;
        }

        return new Source($host, $this->medium);
    }

    /**
     * A shop answering on both www. and the bare host (e.g. redirecting from one to the other) would
     * otherwise be recorded as its own referral
     */
    private static function withoutWww(?string $host): ?string
    {
        return null !== $host && str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
