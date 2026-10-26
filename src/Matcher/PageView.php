<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Matcher;

/**
 * A page view to match a traffic source for: the URL of the visited page and its referrer, as
 * reported by the tracking script
 */
final class PageView
{
    /**
     * The host of the visited page, lowercased, or null if the URL has none
     */
    public readonly ?string $host;

    /** @var array<array-key, string> */
    private readonly array $queryParameters;

    public function __construct(
        public readonly string $url,
        public readonly ?string $referrer = null,
    ) {
        $host = parse_url($url, \PHP_URL_HOST);
        $this->host = is_string($host) && '' !== $host ? strtolower($host) : null;

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $queryParameters);
        $this->queryParameters = array_filter($queryParameters, 'is_string');
    }

    /**
     * Returns the value of a query parameter of the visited page, or null if it's missing or not a
     * string (e.g. ?utm_source[]=x)
     */
    public function getQueryParameter(string $name): ?string
    {
        return $this->queryParameters[$name] ?? null;
    }
}
