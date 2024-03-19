<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Parser;

final class Referrer
{
    public function __construct(
        public readonly string $medium,
        public readonly ?string $source = null,
    ) {
    }
}
