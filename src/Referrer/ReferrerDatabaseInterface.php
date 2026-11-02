<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Referrer;

use Setono\SyliusConversionAttributionPlugin\Matcher\Source;

interface ReferrerDatabaseInterface
{
    /**
     * Returns the source (and medium, if known) a referral from the given URL counts as, or null if the
     * site isn't in the database
     */
    public function lookup(string $referrerUrl): ?Source;
}
