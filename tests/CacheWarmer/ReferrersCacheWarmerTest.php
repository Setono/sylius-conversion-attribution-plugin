<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Setono\SyliusConversionAttributionPlugin\CacheWarmer\ReferrersCacheWarmer;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParser;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

/**
 * The referrer database is read from a local file here, so these tests don't need network access.
 * ReferrerParserFunctionalTest covers the real download
 */
final class ReferrersCacheWarmerTest extends TestCase
{
    private string $dir;

    private string $cacheFile;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/setono-sylius-conversion-attribution-' . bin2hex(random_bytes(8));
        mkdir($this->dir);

        $this->cacheFile = $this->dir . '/referrers.php';
    }

    protected function tearDown(): void
    {
        $files = glob($this->dir . '/*');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->dir);
    }

    /**
     * @test
     */
    public function it_is_optional(): void
    {
        // A failed download must not fail the cache warmup, and thereby a deploy
        self::assertTrue((new ReferrersCacheWarmer($this->cacheFile))->isOptional());
    }

    /**
     * @test
     */
    public function it_warms_up_the_referrer_database(): void
    {
        $database = $this->writeDatabase((string) json_encode([
            'search' => [
                'Google' => ['domains' => ['google.com']],
                'Google Product Search' => ['domains' => ['google.ac/products']],
            ],
            'social' => [
                'Facebook' => ['domains' => ['facebook.com', 'm.facebook.com']],
            ],
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $warmer = new ReferrersCacheWarmer($this->cacheFile, $database);
        $warmer->setLogger($logger);
        $warmer->warmUp($this->dir);

        $cache = new PhpArrayAdapter($this->cacheFile, new NullAdapter());
        self::assertSame(['source' => 'Google', 'medium' => 'search'], $cache->getItem('google.com')->get());
        // Slashes are reserved characters in PSR-6 keys, so paths are stored with pipes instead
        self::assertSame(['source' => 'Google Product Search', 'medium' => 'search'], $cache->getItem('google.ac|products')->get());
        self::assertSame(['source' => 'Facebook', 'medium' => 'social'], $cache->getItem('facebook.com')->get());
        self::assertSame(['source' => 'Facebook', 'medium' => 'social'], $cache->getItem('m.facebook.com')->get());
        self::assertFalse($cache->getItem('example.com')->isHit());

        // The parser reads what the warmer writes
        $referrer = (new ReferrerParser($cache))->parse('https://www.google.ac/products/shoes');
        self::assertSame('search', $referrer->medium);
        self::assertSame('Google Product Search', $referrer->source);
    }

    /**
     * @test
     */
    public function it_logs_a_warning_if_the_database_cannot_be_downloaded(): void
    {
        $database = $this->dir . '/missing.json';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Could not download the referrers database from {url}; referrer-based attribution will be degraded until the next successful cache warmup.',
            ['url' => $database],
        );

        $warmer = new ReferrersCacheWarmer($this->cacheFile, $database);
        $warmer->setLogger($logger);

        self::assertSame([], $warmer->warmUp($this->dir));
        self::assertFileDoesNotExist($this->cacheFile);
    }

    /**
     * @test
     */
    public function it_logs_a_warning_if_the_database_cannot_be_decoded(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Could not decode the referrers database: {message}',
            ['message' => 'Syntax error'],
        );

        $warmer = new ReferrersCacheWarmer($this->cacheFile, $this->writeDatabase('{"search": '));
        $warmer->setLogger($logger);

        self::assertSame([], $warmer->warmUp($this->dir));
        self::assertFileDoesNotExist($this->cacheFile);
    }

    /**
     * @test
     */
    public function it_does_not_need_a_logger(): void
    {
        $warmer = new ReferrersCacheWarmer($this->cacheFile, $this->dir . '/missing.json');

        self::assertSame([], $warmer->warmUp($this->dir));
    }

    private function writeDatabase(string $contents): string
    {
        $file = $this->dir . '/referers.json';
        file_put_contents($file, $contents);

        return $file;
    }
}
