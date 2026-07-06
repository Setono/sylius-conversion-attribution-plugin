<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\CacheWarmer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class ReferrersCacheWarmer implements CacheWarmerInterface
{
    private const REFERRERS_URL = 'https://s3-eu-west-1.amazonaws.com/snowplow-hosted-assets/third-party/referer-parser/referers-latest.json';

    public function __construct(
        private readonly string $phpArrayFile,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir): array
    {
        // Use an explicit timeout so a slow/unreachable host can't stall cache warmup (and thereby a deploy)
        $context = stream_context_create(['http' => ['timeout' => 5], 'https' => ['timeout' => 5]]);
        $json = @file_get_contents(self::REFERRERS_URL, false, $context);
        if (false === $json) {
            $this->logger?->warning('Could not download the referrers database from {url}; referrer-based attribution will be degraded until the next successful cache warmup.', ['url' => self::REFERRERS_URL]);

            return [];
        }

        try {
            /** @var array<string, array<string, array{domains: list<string>}>> $data */
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger?->warning('Could not decode the referrers database: {message}', ['message' => $e->getMessage()]);

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

        return (new PhpArrayAdapter($this->phpArrayFile, new NullAdapter()))->warmUp($values);
    }
}
