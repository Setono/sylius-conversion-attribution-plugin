<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusConversionAttributionPlugin\DependencyInjection\SetonoSyliusConversionAttributionExtension;

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
    public function it_loads_event_subscriber_if_tag_bag_bundle_is_enabled(): void
    {
        $this->setParameter('kernel.bundles', ['SetonoTagBagBundle' => true]);

        $this->load();

        $this->assertContainerBuilderHasService('setono_sylius_conversion_attribution.event_subscriber.add_javascript');
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

        $this->assertContainerBuilderNotHasService('setono_sylius_conversion_attribution.event_subscriber.add_javascript');
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
