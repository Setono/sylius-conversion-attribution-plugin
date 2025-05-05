<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusConversionAttributionPlugin\DependencyInjection\SetonoSyliusConversionAttributionExtension;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddJavascriptSubscriber;

final class SetonoSyliusConversionAttributionExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusConversionAttributionExtension(),
        ];
    }

    /**
     * @test
     */
    public function it_sets_cpc_parameters(): void
    {
        $this->load([
            'javascript' => false,
            'query_parameters' => [
                'facebook' => ['enabled' => false], // should exclude facebook from the resolved list
                'generic' => ['matches' => ['ref']], // 'ref' should only occur once on the resolved list
                'x' => ['matches' => ['xclid']], // should add 'xclid' to the list
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.query_parameters', [
            'google' => ['matches' => ['gclid', 'gbraid', 'wbraid'], 'source' => 'google', 'medium' => 'cpc', 'campaign' => null],
            'microsoft' => ['matches' => ['msclkid'], 'source' => 'bing', 'medium' => 'cpc', 'campaign' => null],
            'generic' => ['matches' => ['source', 'ref'], 'source' => null, 'medium' => null, 'campaign' => null],
            'tiktok' => ['matches' => ['ttclid'], 'source' => 'tiktok', 'medium' => 'cpc', 'campaign' => null],
            'x' => ['matches' => ['twclid', 'xclid'], 'source' => 'x', 'medium' => 'cpc', 'campaign' => null],
        ]);
    }

    /**
     * @test
     */
    public function it_loads_event_subscriber_if_tag_bag_bundle_is_enabled(): void
    {
        $this->setParameter('kernel.bundles', ['SetonoTagBagBundle' => true]);

        $this->load();

        $this->assertContainerBuilderHasService(AddJavascriptSubscriber::class);
    }

    /**
     * @test
     */
    public function it_does_not_load_event_subscriber_if_javascript_is_disabled(): void
    {
        $this->setParameter('kernel.bundles', ['SetonoTagBagBundle' => true]);

        $this->load([
            'javascript' => [
                'enabled' => false,
            ],
        ]);

        $this->assertContainerBuilderNotHasService(AddJavascriptSubscriber::class);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_bundles_are_not_set(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('#^You need to install setono/tag-bag-bundle to use the javascript feature#');

        $this->load();
    }

    /**
     * @test
     */
    public function it_throws_exception_if_bundles_are_empty(): void
    {
        $this->setParameter('kernel.bundles', []);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('#^You need to install setono/tag-bag-bundle to use the javascript feature#');

        $this->load();
    }
}
