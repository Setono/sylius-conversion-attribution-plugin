<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\CacheWarmer;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class ReferrersCacheWarmer implements CacheWarmerInterface
{
    public function __construct(private readonly AdapterInterface $cache)
    {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir): array
    {
        if (!$this->cache instanceof PhpArrayAdapter) {
            return [];
        }

        $json = file_get_contents('https://s3-eu-west-1.amazonaws.com/snowplow-hosted-assets/third-party/referer-parser/referers-latest.json');
        if (false === $json) {
            return [];
        }

        try {
            /** @var array<string, array<string, array{domains: list<string>}>> $data */
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $values = [];

        foreach ($data as $medium => $referrers) {
            foreach ($referrers as $source => $referrer) {
                foreach ($referrer['domains'] as $domain) {
                    $domain = str_replace('/', '|', $domain);

                    $values[$domain] = [
                        'source' => $source,
                        'medium' => $medium,
                    ];
                }
            }
        }

        return $this->cache->warmUp($values);
    }
}
