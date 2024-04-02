<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Parser;

use Symfony\Component\Cache\Adapter\AdapterInterface;

final class ReferrerParser implements ReferrerParserInterface
{
    public function __construct(private readonly AdapterInterface $cache)
    {
    }

    /**
     * A lot of the code here is copied from https://github.com/snowplow-referer-parser/php-referer-parser
     */
    public function parse(string $referrer): Referrer
    {
        $parsedReferrer = self::parseUrl($referrer);
        if (null === $parsedReferrer) {
            return Referrer::invalid();
        }

        $referrerLookup = $this->lookup($parsedReferrer['host'], $parsedReferrer['path']);

        if (null === $referrerLookup) {
            return Referrer::unknown();
        }

        if (!isset($referrerLookup['medium'], $referrerLookup['source'])) {
            return Referrer::invalid();
        }

        if (!is_string($referrerLookup['medium']) || !is_string($referrerLookup['source'])) {
            return Referrer::invalid();
        }

        return new Referrer(Medium::from($referrerLookup['medium']), $referrerLookup['source']);
    }

    private function lookup(string $host, string $path): ?array
    {
        $referrer = $this->lookupPath($host, $path);

        if (null !== $referrer) {
            return $referrer;
        }

        return $this->lookupHost($host);
    }

    private function lookupPath(string $host, string $path): ?array
    {
        $referer = $this->lookupHost($host, $path);

        if (null !== $referer) {
            return $referer;
        }

        $pos = strrpos($path, '/');
        if (false === $pos) {
            return null;
        }

        $path = substr($path, 0, $pos);

        return $this->lookupPath($host, $path);
    }

    private function lookupHost(string $host, string $path = ''): ?array
    {
        do {
            $cacheItem = $this->cache->getItem($host . self::escapePath($path));
            if ($cacheItem->isHit()) {
                /** @var mixed $res */
                $res = $cacheItem->get();
                if (is_array($res)) {
                    return $res;
                }
            }

            $pos = strpos($host, '.');
            if (false === $pos) {
                return null;
            }

            $host = substr($host, $pos + 1);
        } while (substr_count($host, '.') > 0);

        return null;
    }

    /**
     * @return array{
     *     scheme: string,
     *     host: string,
     *     path: string,
     *     query: string|null,
     *     fragment?: string,
     *     user?: string,
     *     pass?: string,
     *     port?: int
     * }|null
     */
    private static function parseUrl(string $url): ?array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'])) {
            return null;
        }

        return array_merge(['query' => null, 'path' => '/'], $parts);
    }

    private static function escapePath(string $path): string
    {
        return str_replace('/', '|', $path);
    }
}
