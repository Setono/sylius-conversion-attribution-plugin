<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

use Webmozart\Assert\Assert;

/**
 * A traffic source matched for a page view. The values are trimmed and a blank medium or campaign is
 * null, so every matcher's result follows the same rules
 */
final class Source
{
    public readonly string $source;

    public readonly ?string $medium;

    public readonly ?string $campaign;

    /**
     * @throws \InvalidArgumentException if the source is blank: a matcher that can't name the source
     *                                   must return null instead, so the next matcher gets a chance
     */
    public function __construct(
        string $source,
        ?string $medium = null,
        ?string $campaign = null,
    ) {
        $source = trim($source);
        Assert::stringNotEmpty($source, 'The source of a match must not be blank. Return null from the matcher instead');

        $this->source = $source;
        $this->medium = self::normalize($medium);
        $this->campaign = self::normalize($campaign);
    }

    /**
     * Returns the value trimmed, or null if it's missing or blank
     */
    private static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
