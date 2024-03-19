<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Parser;

interface ReferrerParserInterface
{
    public function parse(string $referrer): Referrer;
}
