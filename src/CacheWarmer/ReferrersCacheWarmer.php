<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\CacheWarmer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusConversionAttributionPlugin\Referrer\ReferrerDatabase;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Downloads Snowplow's referer database and builds the referrer database from it
 */
final class ReferrersCacheWarmer implements CacheWarmerInterface, LoggerAwareInterface
{
    private const REFERRERS_URL = 'https://s3-eu-west-1.amazonaws.com/snowplow-hosted-assets/third-party/referer-parser/referers-latest.json';

    private LoggerInterface $logger;

    public function __construct(
        private readonly ReferrerDatabase $referrerDatabase,
        // Where Snowplow's referer database is downloaded from
        private readonly string $url = self::REFERRERS_URL,
    ) {
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir): array
    {
        // Use an explicit timeout so a slow/unreachable host can't stall cache warmup (and thereby a deploy).
        // The http context options apply to https:// URLs too
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $json = @file_get_contents($this->url, context: $context);
        if (false === $json) {
            $this->logger->warning('Could not download the referrers database from {url}; referrer-based attribution will be degraded until the next successful cache warmup.', ['url' => $this->url]);

            return [];
        }

        try {
            $this->referrerDatabase->build($json);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Could not build the referrer database: {message}', ['message' => $e->getMessage()]);
        }

        return [];
    }
}
