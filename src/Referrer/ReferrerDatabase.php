<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Referrer;

use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Symfony\Component\Config\ResourceCheckerConfigCache;

/**
 * Snowplow's referer database (https://github.com/snowplow-referer-parser/referer-parser), stored as a
 * PHP file that returns an array, so opcache keeps it in shared memory
 */
final class ReferrerDatabase implements ReferrerDatabaseInterface
{
    /**
     * Snowplow's medium for sites it knows but doesn't classify (e.g. Google Maps). They are stored
     * without a medium, so the caller decides which one a referral from them gets
     */
    private const UNKNOWN_MEDIUM = 'unknown';

    /** @var array<string, array{string, ?string}>|null */
    private ?array $referrers = null;

    public function __construct(private readonly string $file)
    {
    }

    /**
     * Builds the database from Snowplow's referers-latest.json
     *
     * @throws \InvalidArgumentException if the JSON isn't shaped like Snowplow's database, in which case
     *                                   the database built before is kept
     */
    public function build(string $snowplowJson): void
    {
        try {
            $data = json_decode($snowplowJson, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(sprintf('The referrer database is not valid JSON: %s', $e->getMessage()), previous: $e);
        }

        // The JSON maps each medium to its sources, and each source to the domains it's found on
        $referrers = [];
        foreach (self::object($data) as $medium => $sources) {
            $medium = (string) $medium;

            foreach (self::object($sources) as $source => $site) {
                foreach (self::object(self::object($site)['domains'] ?? null) as $domain) {
                    if (!is_string($domain)) {
                        throw self::unexpectedFormat();
                    }

                    // A domain may have a path (e.g. google.ac/products). Only the host is case-insensitive
                    $slash = strpos($domain, '/');
                    $key = false === $slash ? strtolower($domain) : strtolower(substr($domain, 0, $slash)) . substr($domain, $slash);

                    $referrers[$key] = [(string) $source, self::UNKNOWN_MEDIUM === $medium ? null : $medium];
                }
            }
        }

        // Writes the file atomically and invalidates it in opcache, the way Symfony writes its own caches
        // (ConfigCache, which adds freshness checks that this file doesn't need, is built on it)
        (new ResourceCheckerConfigCache($this->file))->write(sprintf("<?php\n\nreturn %s;\n", var_export($referrers, true)));
        $this->referrers = $referrers;
    }

    public function lookup(string $referrerUrl): ?Source
    {
        $url = parse_url($referrerUrl);
        if (!is_array($url) || !isset($url['scheme'], $url['host']) || !in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $referrers = $this->referrers();
        $labels = explode('.', strtolower($url['host']));

        // Some sites are listed with a path (e.g. google.ac/products), so the longest matching path wins:
        // each path prefix is tried with the host itself and then each parent domain down to (but not
        // including) the top level domain
        foreach (self::pathPrefixes($url['path'] ?? '') as $path) {
            for ($i = 0, $parents = count($labels) - 1; $i < $parents; ++$i) {
                $referrer = $referrers[implode('.', array_slice($labels, $i)) . $path] ?? null;
                if (null !== $referrer) {
                    return new Source($referrer[0], $referrer[1]);
                }
            }
        }

        return null;
    }

    /**
     * Returns the prefixes of a URL path, longest first, e.g. /a/b, /a and '' for /a/b
     *
     * @return list<string>
     */
    private static function pathPrefixes(string $path): array
    {
        $prefixes = [''];
        $prefix = '';
        foreach (explode('/', ltrim($path, '/')) as $segment) {
            $prefix .= '/' . $segment;
            $prefixes[] = $prefix;
        }

        return array_reverse($prefixes);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function object(mixed $value): array
    {
        if (!is_array($value)) {
            throw self::unexpectedFormat();
        }

        return $value;
    }

    private static function unexpectedFormat(): \InvalidArgumentException
    {
        return new \InvalidArgumentException('The referrer database is not shaped like Snowplow\'s referers-latest.json');
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    private function referrers(): array
    {
        if (null === $this->referrers) {
            // include, not require: if the file disappears after the is_file() check (e.g. while a deploy
            // swaps the cache directory), require would end the process with a fatal error, while include
            // only warns and returns false, so the lookup finds nothing instead
            $included = is_file($this->file) ? include $this->file : [];

            /** @var array<string, array{string, ?string}> $referrers */
            $referrers = is_array($included) ? $included : [];
            $this->referrers = $referrers;
        }

        return $this->referrers;
    }
}
