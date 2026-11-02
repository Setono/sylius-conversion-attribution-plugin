<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Referrer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Referrer\ReferrerDatabase;

/**
 * Builds small referrer databases in the same format as Snowplow's referers-latest.json.
 * ReferrerDatabaseFunctionalTest covers the real one
 */
final class ReferrerDatabaseTest extends TestCase
{
    private const SNOWPLOW = [
        'search' => [
            'Google' => ['domains' => ['google.com', 'google.ac']],
            'Google Product Search' => ['domains' => ['google.ac/products']],
        ],
        'social' => [
            'Facebook' => ['domains' => ['facebook.com']],
        ],
    ];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/setono-sylius-conversion-attribution-' . bin2hex(random_bytes(8));
        mkdir($this->dir);
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
    public function it_looks_up_a_known_site_in_the_database_it_built(): void
    {
        $match = $this->database(self::SNOWPLOW)->lookup('https://google.com/');

        self::assertEquals(new Source('Google', 'search'), $match);
    }

    /**
     * @test
     */
    public function it_looks_up_a_subdomain_by_its_parent_domains(): void
    {
        $match = $this->database(self::SNOWPLOW)->lookup('https://www.m.facebook.com/l.php?u=x');

        self::assertEquals(new Source('Facebook', 'social'), $match);
    }

    /**
     * @test
     */
    public function it_prefers_the_longest_matching_path(): void
    {
        $database = $this->database(self::SNOWPLOW);

        self::assertEquals(new Source('Google Product Search', 'search'), $database->lookup('https://www.google.ac/products/shoes?q=red'));
        self::assertEquals(new Source('Google Product Search', 'search'), $database->lookup('https://google.ac/products'));
        self::assertEquals(new Source('Google', 'search'), $database->lookup('https://www.google.ac/productsearch'));
        // A listed path further down the path doesn't count
        self::assertEquals(new Source('Google', 'search'), $database->lookup('https://www.google.ac/shoes/products'));
    }

    /**
     * @test
     */
    public function it_looks_up_http_and_https_referrers(): void
    {
        $database = $this->database(self::SNOWPLOW);

        self::assertEquals(new Source('Google', 'search'), $database->lookup('http://google.com/'));
        self::assertEquals(new Source('Google', 'search'), $database->lookup('HTTPS://google.com/'));
    }

    /**
     * @test
     */
    public function it_ignores_the_case_of_hosts(): void
    {
        // Snowplow's database lists e.g. Sozluk.com
        $database = $this->database(['search' => [
            'Google' => ['domains' => ['google.com']],
            'Sozluk' => ['domains' => ['Sozluk.com']],
            'Example Search' => ['domains' => ['Example.org/Search']],
        ]]);

        self::assertEquals(new Source('Google', 'search'), $database->lookup('https://WWW.Google.COM/'));
        self::assertEquals(new Source('Sozluk', 'search'), $database->lookup('https://sozluk.com/'));
        self::assertEquals(new Source('Example Search', 'search'), $database->lookup('https://example.org/Search/results'));
    }

    /**
     * @test
     */
    public function it_never_matches_a_top_level_domain_alone(): void
    {
        $database = $this->database(['search' => ['Commercial' => ['domains' => ['com']]]]);

        self::assertNull($database->lookup('https://shop.com/'));
        self::assertNull($database->lookup('https://www.shop.com/'));
    }

    /**
     * @test
     */
    public function it_keeps_names_that_look_like_numbers_as_names(): void
    {
        // JSON object keys like these decode to integers in PHP
        $match = $this->databaseFromJson('{"7": {"42": {"domains": ["example.org"]}}}')->lookup('https://example.org/');

        self::assertEquals(new Source('42', '7'), $match);
    }

    /**
     * @test
     */
    public function it_finds_sites_listed_without_a_known_medium(): void
    {
        // Snowplow lists e.g. Google Maps and Google Drive with the medium "unknown"
        $database = $this->database(['unknown' => ['Google' => ['domains' => ['maps.google.com']]]]);

        self::assertEquals(new Source('Google'), $database->lookup('https://maps.google.com/'));
    }

    /**
     * @test
     */
    public function it_finds_nothing_before_it_is_built(): void
    {
        // e.g. the cache warmup couldn't download Snowplow's database
        self::assertNull((new ReferrerDatabase($this->dir . '/referrers.php'))->lookup('https://google.com/'));
    }

    /**
     * @test
     */
    public function it_finds_nothing_in_a_file_that_is_not_a_database(): void
    {
        // e.g. an empty file, or another file at the same path
        $file = $this->dir . '/referrers.php';
        file_put_contents($file, "<?php\n");

        self::assertNull((new ReferrerDatabase($file))->lookup('https://google.com/'));
    }

    /**
     * @test
     */
    public function it_finds_what_it_built_after_looking_up_before(): void
    {
        $database = new ReferrerDatabase($this->dir . '/referrers.php');
        self::assertNull($database->lookup('https://google.com/'));

        $database->build((string) json_encode(self::SNOWPLOW));

        self::assertEquals(new Source('Google', 'search'), $database->lookup('https://google.com/'));
    }

    /**
     * @test
     *
     * @dataProvider invalidSnowplowJson
     */
    public function it_keeps_the_database_if_the_new_one_is_invalid(string $snowplowJson): void
    {
        $this->database(self::SNOWPLOW);
        $file = $this->dir . '/referrers.php';

        try {
            (new ReferrerDatabase($file))->build($snowplowJson);
            self::fail('An invalid database was built');
        } catch (\InvalidArgumentException) {
        }

        self::assertEquals(new Source('Google', 'search'), (new ReferrerDatabase($file))->lookup('https://google.com/'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSnowplowJson(): iterable
    {
        yield 'not json' => ['{"search": '];
        yield 'not an object' => ['"search"'];
        yield 'sources not an object' => ['{"search": "Google"}'];
        yield 'site not an object' => ['{"search": {"Google": "google.com"}}'];
        yield 'no domains' => ['{"search": {"Google": {"hosts": ["google.com"]}}}'];
        yield 'domains not strings' => ['{"search": {"Google": {"domains": [{"host": "google.com"}]}}}'];
    }

    /**
     * @test
     *
     * @dataProvider unknownReferrers
     */
    public function it_does_not_find_unknown_or_unusable_referrers(string $referrerUrl): void
    {
        self::assertNull($this->database(self::SNOWPLOW)->lookup($referrerUrl));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownReferrers(): iterable
    {
        yield 'unknown site' => ['https://blog.example/post'];
        yield 'only the top level domain in common' => ['https://example.com/'];
        // Characters that PSR-6 cache keys reserve once turned a lookup into an HTTP 500
        yield 'reserved characters in the path' => ['https://en.wikipedia.org/wiki/Foo_(disambiguation)'];
        yield 'user info and port' => ['https://user:pass@blog.example:8080/a@b'];
        yield 'another scheme' => ['ftp://google.com/'];
        yield 'not a url' => ['google.com'];
        yield 'empty' => [''];
    }

    /**
     * Builds the database with one instance and returns another one reading the same file, the way
     * the cache warmer and the requests that look referrers up do
     *
     * @param array<string, array<string, array{domains: list<string>}>> $snowplow
     */
    private function database(array $snowplow): ReferrerDatabase
    {
        return $this->databaseFromJson((string) json_encode($snowplow));
    }

    private function databaseFromJson(string $snowplowJson): ReferrerDatabase
    {
        $file = $this->dir . '/referrers.php';
        (new ReferrerDatabase($file))->build($snowplowJson);

        return new ReferrerDatabase($file);
    }
}
