<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusConversionAttributionPlugin\Attribution\OrderAttributorInterface;
use Setono\SyliusConversionAttributionPlugin\Command\AttributeCommand;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackAction;
use Setono\SyliusConversionAttributionPlugin\DependencyInjection\SetonoSyliusConversionAttributionExtension;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddClientIdSubscriber;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddJavascriptSubscriber;
use Setono\SyliusConversionAttributionPlugin\Model\Attribution;
use Setono\SyliusConversionAttributionPlugin\Repository\AttributionRepository;
use Setono\SyliusConversionAttributionPlugin\Repository\SourceRepository;
use Setono\TagBagBundle\SetonoTagBagBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class SetonoSyliusConversionAttributionExtensionTest extends AbstractExtensionTestCase
{
    private const SCRIPT = __DIR__ . '/../../src/Resources/public/js/track.js';

    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusConversionAttributionExtension(),
        ];
    }

    /**
     * @test
     */
    public function it_loads_the_services_and_sets_the_parameters(): void
    {
        $this->setParameter('kernel.bundles', ['SetonoTagBagBundle' => SetonoTagBagBundle::class]);

        $this->load([
            'session_timeout' => 3600,
            'lookback_window' => 60,
        ]);

        $this->assertContainerBuilderHasService(TrackAction::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.javascript.enabled', true);
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.session_timeout', 3600);
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.referrers.cache.file', '%kernel.cache_dir%/referrers.php');
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.model.source.class');
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.lookback_window', 60);
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.model.attribution.class', Attribution::class);
        $this->assertContainerBuilderHasService(OrderAttributorInterface::class);
        $this->assertContainerBuilderHasService(AttributeCommand::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.repository.attribution.class', AttributionRepository::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.repository.source.class', SourceRepository::class);
    }

    /**
     * A host that points the ClientContextInterface alias at its own context, instead of decorating the default
     * one like client-bundle does, would otherwise track page views and stamp orders with different client ids
     *
     * @test
     */
    public function it_reads_the_client_id_of_page_views_and_orders_from_the_same_client_context(): void
    {
        $this->setParameter('kernel.bundles', ['SetonoTagBagBundle' => SetonoTagBagBundle::class]);

        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TrackAction::class, 3, new Reference('setono_client.client_context.default'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(AddClientIdSubscriber::class, 0, new Reference('setono_client.client_context.default'));
    }

    /**
     * @test
     */
    public function it_loads_event_subscriber_if_tag_bag_bundle_is_enabled(): void
    {
        $this->setParameter('kernel.bundles', [
            'FrameworkBundle' => FrameworkBundle::class,
            'SetonoTagBagBundle' => SetonoTagBagBundle::class,
        ]);

        $this->load();

        $this->assertContainerBuilderHasService(AddJavascriptSubscriber::class);
        $this->assertContainerBuilderHasParameter(
            'setono_sylius_conversion_attribution.javascript.version',
            substr((string) hash_file('xxh128', self::SCRIPT), 0, 12),
        );
    }

    /**
     * @test
     */
    public function it_tracks_the_tracking_script_so_editing_it_rebuilds_the_container(): void
    {
        $this->setParameter('kernel.bundles', ['SetonoTagBagBundle' => SetonoTagBagBundle::class]);

        $this->load();

        $trackedFiles = array_map(
            static fn (FileResource $resource): string => $resource->getResource(),
            array_values(array_filter($this->container->getResources(), static fn (object $resource): bool => $resource instanceof FileResource)),
        );

        self::assertContains(realpath(self::SCRIPT), $trackedFiles);
    }

    /**
     * @test
     */
    public function it_does_not_load_event_subscriber_if_javascript_is_disabled(): void
    {
        $this->setParameter('kernel.bundles', ['SetonoTagBagBundle' => SetonoTagBagBundle::class]);

        $this->load([
            'javascript' => [
                'enabled' => false,
            ],
        ]);

        $this->assertContainerBuilderNotHasService(AddJavascriptSubscriber::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.javascript.enabled', false);
        self::assertFalse($this->container->hasParameter('setono_sylius_conversion_attribution.javascript.version'));
    }

    /**
     * @test
     */
    public function it_leaves_out_disabled_query_parameters(): void
    {
        $this->load([
            'javascript' => false,
            'query_parameters' => [
                'google' => ['matches' => ['gclid'], 'source' => 'google', 'medium' => 'cpc'],
                'generic' => ['matches' => ['ref'], 'enabled' => false],
            ],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_conversion_attribution.query_parameters', [
            'google' => ['matches' => ['gclid'], 'source' => 'google', 'medium' => 'cpc', 'enabled' => true, 'campaign' => null],
        ]);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_tag_bag_bundle_is_not_enabled(): void
    {
        $this->setParameter('kernel.bundles', ['FrameworkBundle' => FrameworkBundle::class]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('#^You need to install setono/tag-bag-bundle to use the javascript feature#');

        $this->load();
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

    /**
     * @test
     */
    public function it_throws_exception_if_bundles_are_not_an_array(): void
    {
        $this->setParameter('kernel.bundles', 'SetonoTagBagBundle');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('#^You need to install setono/tag-bag-bundle to use the javascript feature#');

        $this->load();
    }

    /**
     * @test
     */
    public function it_prepends_the_default_query_parameters_and_the_admin_order_block(): void
    {
        $container = new ContainerBuilder();
        (new SetonoSyliusConversionAttributionExtension())->prepend($container);

        self::assertSame([[
            'query_parameters' => [
                'facebook' => [
                    'matches' => ['fbclid'], 'source' => 'facebook', 'medium' => 'cpc',
                ],
                'google' => [
                    'matches' => ['gclid', 'gbraid', 'wbraid'], 'source' => 'google', 'medium' => 'cpc',
                ],
                'microsoft' => [
                    'matches' => ['msclkid'], 'source' => 'bing', 'medium' => 'cpc',
                ],
                'generic' => [
                    'matches' => ['source', 'ref'],
                ],
                'tiktok' => [
                    'matches' => ['ttclid'], 'source' => 'tiktok', 'medium' => 'cpc',
                ],
                'x' => [
                    'matches' => ['twclid'], 'source' => 'x', 'medium' => 'cpc',
                ],
            ],
        ]], $container->getExtensionConfig('setono_sylius_conversion_attribution'));

        self::assertSame([[
            'events' => [
                'sylius.admin.order.show.sidebar' => [
                    'blocks' => [
                        'setono_sylius_conversion_attribution_conversion_path' => [
                            'template' => '@SetonoSyliusConversionAttributionPlugin/order/attribution.html.twig',
                            'priority' => 40,
                        ],
                    ],
                ],
            ],
        ]], $container->getExtensionConfig('sylius_ui'));
    }
}
