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

    /**
     * The referrer, trimmed, or null if there is none or it's blank
     */
    public readonly ?string $referrer;

    /**
     * The host of the referrer, lowercased, or null if there is no referrer or it has no host. Any
     * scheme counts, e.g. android-app://com.google.android.gm (the Gmail app)
     */
    public readonly ?string $referrerHost;

    /** @var array<array-key, string> */
    private readonly array $queryParameters;

    public function __construct(
        public readonly string $url,
        ?string $referrer = null,
    ) {
        $this->host = self::hostOf($url);
        $this->referrer = null === $referrer ? null : self::normalize($referrer);
        $this->referrerHost = null === $this->referrer ? null : self::hostOf($this->referrer);

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $queryParameters);
        $normalizedQueryParameters = [];
        foreach ($queryParameters as $name => $value) {
            $value = is_string($value) ? self::normalize($value) : null;
            if (null !== $value) {
                $normalizedQueryParameters[$name] = $value;
            }
        }
        $this->queryParameters = $normalizedQueryParameters;
    }

    private static function hostOf(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return is_string($host) && '' !== $host ? strtolower($host) : null;
    }

    /**
     * Returns the value trimmed, or null if it's blank
     */
    private static function normalize(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
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
