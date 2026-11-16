<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusConversionAttributionPlugin\DependencyInjection\SetonoSyliusConversionAttributionExtension;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\Source;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Referrer\ReferrerDatabase;
use Setono\SyliusConversionAttributionPlugin\SetonoSyliusConversionAttributionPlugin;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Matches page views with the source matcher the plugin wires up (the service the track action uses), with
 * the default configuration and a referrer database that knows www.google.com and facebook.com
 */
final class SourceMatchingTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/setono-sylius-conversion-attribution-' . bin2hex(random_bytes(8));
        mkdir($this->dir);

        (new ReferrerDatabase($this->dir . '/referrers.php'))->build((string) json_encode([
            'search' => ['Google' => ['domains' => ['www.google.com']]],
            'social' => ['Facebook' => ['domains' => ['facebook.com']]],
        ]));
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
     *
     * @dataProvider pageViews
     */
    public function it_matches_the_source_of_a_page_view(string $query, ?string $referrer, ?Source $expected): void
    {
        self::assertEquals($expected, $this->matcher()->match(new PageView('https://shop.example/?' . $query, $referrer)));
    }

    /**
     * A null match is stored as direct
     *
     * @return iterable<string, array{string, ?string, ?Source}>
     */
    public static function pageViews(): iterable
    {
        yield 'UTM parameters' => [
            'utm_source=newsletter&utm_medium=email&utm_campaign=fall', null,
            new Source('newsletter', 'email', 'fall'),
        ];

        yield 'a click id with UTM parameters' => [
            'gclid=abc&utm_source=google&utm_medium=cpc&utm_campaign=spring_sale', null,
            new Source('google', 'cpc', 'spring_sale'),
        ];

        yield 'a click id with only a utm_campaign' => [
            'gclid=abc&utm_campaign=spring_sale', null,
            new Source('google', 'cpc', 'spring_sale'),
        ];

        yield 'a facebook click id' => [
            'fbclid=abc', null,
            new Source('facebook', 'social'),
        ];

        yield 'a facebook ad' => [
            'fbclid=abc&utm_source=facebook&utm_medium=paid_social&utm_campaign=post', null,
            new Source('facebook', 'paid_social', 'post'),
        ];

        yield 'a generic source parameter with UTM parameters naming another source' => [
            'ref=partner&utm_source=newsletter&utm_medium=email&utm_campaign=november', null,
            new Source('newsletter', 'email', 'november'),
        ];

        yield 'a utm_source from a referrer naming another source' => [
            'utm_source=newsletter', 'https://facebook.com/',
            new Source('newsletter'),
        ];

        yield 'a utm_source from a referrer naming the same source in another case' => [
            'utm_source=google&utm_campaign=x', 'https://www.google.com/',
            new Source('google', 'search', 'x'),
        ];

        yield 'a blank utm_source from a referrer' => [
            'utm_source=&utm_medium=cpc', 'https://www.google.com/',
            new Source('Google', 'cpc'),
        ];

        yield 'UTM parameters without a source and without a referrer' => [
            'utm_medium=email&utm_campaign=black-friday', null,
            new Source('(not set)', 'email', 'black-friday'),
        ];

        yield 'UTM parameters without a source from a referrer on the shop' => [
            'utm_medium=email&utm_campaign=black-friday', 'https://shop.example/cart',
            new Source('(not set)', 'email', 'black-friday'),
        ];

        yield 'only a blank utm_source' => ['utm_source=', null, null];

        yield 'nothing' => ['', null, null];
    }

    /**
     * @test
     *
     * @dataProvider hostMatcherPriorities
     */
    public function it_applies_the_utm_parameters_to_the_match_of_a_matcher_the_host_app_tags(int $priority): void
    {
        $hostMatcher = $this->createStub(SourceMatcherInterface::class);
        $hostMatcher->method('match')->willReturn(new Source('partner', 'affiliate', 'evergreen'));

        self::assertEquals(
            new Source('partner', 'affiliate', 'spring_sale'),
            $this->matcher([$priority => $hostMatcher])->match(new PageView('https://shop.example/?utm_campaign=spring_sale')),
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function hostMatcherPriorities(): iterable
    {
        yield 'above the click ids' => [400];
        yield 'between the click ids and the referrer' => [200];
        yield 'below the referrer' => [50];
    }

    /**
     * @param array<int, SourceMatcherInterface> $hostMatchers the host app's matchers by the priority they're tagged with
     */
    private function matcher(array $hostMatchers = []): SourceMatcherInterface
    {
        $container = new ContainerBuilder();
        $extension = new SetonoSyliusConversionAttributionExtension();
        $extension->prepend($container);
        $extension->load([...$container->getExtensionConfig('setono_sylius_conversion_attribution'), ['javascript' => false]], $container);
        $container->setParameter('setono_sylius_conversion_attribution.referrers.cache.file', $this->dir . '/referrers.php');

        foreach (array_keys($hostMatchers) as $priority) {
            $container->register('app.source_matcher.' . $priority, SourceMatcherInterface::class)
                ->setSynthetic(true)
                ->addTag('setono_sylius_conversion_attribution.source_matcher', ['priority' => $priority]);
        }

        // Only the passes the matchers need: compiling the whole container would need Doctrine, Sylius, …
        (new ResolveClassPass())->process($container);
        (new SetonoSyliusConversionAttributionPlugin())->build($container);
        foreach ($container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof CompositeCompilerPass) {
                $pass->process($container);
            }
        }

        // Setting a service drops its definition and tags, so only after the passes have read them
        foreach ($hostMatchers as $priority => $hostMatcher) {
            $container->set('app.source_matcher.' . $priority, $hostMatcher);
        }

        $matcher = $container->get(SourceMatcherInterface::class);
        self::assertInstanceOf(SourceMatcherInterface::class, $matcher);

        return $matcher;
    }
}
