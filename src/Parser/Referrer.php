<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Parser;

final class Referrer
{
    public function __construct(
        public readonly Medium $medium,
        public readonly ?string $source = null,
    ) {
    }

    public static function invalid(): self
    {
        return new self(Medium::invalid);
    }

    public static function unknown(): self
    {
        return new self(Medium::unknown);
    }
}
