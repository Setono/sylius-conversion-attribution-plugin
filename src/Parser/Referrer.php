<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Parser;

final class Referrer
{
    final public const MEDIUM_SEARCH = 'search';

    final public const MEDIUM_SOCIAL = 'social';

    final public const MEDIUM_UNKNOWN = 'unknown';

    final public const MEDIUM_INTERNAL = 'internal';

    final public const MEDIUM_EMAIL = 'email';

    final public const MEDIUM_INVALID = 'invalid';

    public function __construct(
        public readonly string $medium,
        public readonly ?string $source = null,
    ) {
    }

    public static function invalid(): self
    {
        return new self(self::MEDIUM_INVALID);
    }

    public static function unknown(): self
    {
        return new self(self::MEDIUM_UNKNOWN);
    }
}
