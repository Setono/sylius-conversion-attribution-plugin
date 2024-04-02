<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

final class Source
{
    public function __construct(
        public readonly string $source,
        public readonly ?string $medium = null,
        public readonly ?string $campaign = null,
    ) {
    }
}
