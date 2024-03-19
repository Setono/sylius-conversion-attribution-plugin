<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Parser;

final class ReferrerParser implements ReferrerParserInterface
{
    public function parse(string $referrer): Referrer
    {
        // todo parse referrer

        return new Referrer('organic', 'google');
    }
}
