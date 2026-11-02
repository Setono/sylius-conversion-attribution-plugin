<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Setono\SyliusConversionAttributionPlugin\CacheWarmer\ReferrersCacheWarmer;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Referrer\ReferrerDatabase;

/**
 * Snowplow's database is read from a local file here, so these tests don't need network access.
 * ReferrerDatabaseFunctionalTest covers the real download
 */
final class ReferrersCacheWarmerTest extends TestCase
{
    private const SNOWPLOW = [
        'search' => [
            'Google' => ['domains' => ['google.com']],
            'Google Product Search' => ['domains' => ['google.ac/products']],
        ],
    ];

    private string $dir;

    private string $databaseFile;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/setono-sylius-conversion-attribution-' . bin2hex(random_bytes(8));
        mkdir($this->dir);

        $this->databaseFile = $this->dir . '/referrers.php';
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
        self::assertTrue((new ReferrersCacheWarmer(new ReferrerDatabase($this->databaseFile)))->isOptional());
    }

    /**
     * @test
     */
    public function it_builds_the_referrer_database(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $warmer = new ReferrersCacheWarmer(new ReferrerDatabase($this->databaseFile), $this->writeSnowplow((string) json_encode(self::SNOWPLOW)));
        $warmer->setLogger($logger);

        self::assertSame([], $warmer->warmUp($this->dir));
        self::assertEquals(new Source('Google Product Search', 'search'), (new ReferrerDatabase($this->databaseFile))->lookup('https://www.google.ac/products/shoes'));
    }

    /**
     * @test
     */
    public function it_logs_a_warning_and_keeps_the_database_if_snowplow_cannot_be_downloaded(): void
    {
        $this->buildDatabase();
        $url = $this->dir . '/missing.json';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Could not download the referrers database from {url}; referrer-based attribution will be degraded until the next successful cache warmup.',
            ['url' => $url],
        );

        $warmer = new ReferrersCacheWarmer(new ReferrerDatabase($this->databaseFile), $url);
        $warmer->setLogger($logger);

        self::assertSame([], $warmer->warmUp($this->dir));
        self::assertEquals(new Source('Google', 'search'), (new ReferrerDatabase($this->databaseFile))->lookup('https://google.com/'));
    }

    /**
     * @test
     */
    public function it_logs_a_warning_and_keeps_the_database_if_snowplow_cannot_be_read(): void
    {
        $this->buildDatabase();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Could not build the referrer database: {message}',
            ['message' => 'The referrer database is not valid JSON: Syntax error'],
        );

        $warmer = new ReferrersCacheWarmer(new ReferrerDatabase($this->databaseFile), $this->writeSnowplow('{"search": '));
        $warmer->setLogger($logger);

        self::assertSame([], $warmer->warmUp($this->dir));
        self::assertEquals(new Source('Google', 'search'), (new ReferrerDatabase($this->databaseFile))->lookup('https://google.com/'));
    }

    /**
     * @test
     */
    public function it_does_not_need_a_logger(): void
    {
        $warmer = new ReferrersCacheWarmer(new ReferrerDatabase($this->databaseFile), $this->dir . '/missing.json');

        self::assertSame([], $warmer->warmUp($this->dir));
    }

    private function buildDatabase(): void
    {
        (new ReferrerDatabase($this->databaseFile))->build((string) json_encode(self::SNOWPLOW));
    }

    private function writeSnowplow(string $contents): string
    {
        $file = $this->dir . '/referers.json';
        file_put_contents($file, $contents);

        return $file;
    }
}
