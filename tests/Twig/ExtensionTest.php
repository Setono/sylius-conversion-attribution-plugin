<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Setono\SyliusConversionAttributionPlugin\Provider\OrderAttributionProviderInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Order;
use Setono\SyliusConversionAttributionPlugin\Twig\Extension;
use Setono\SyliusConversionAttributionPlugin\Twig\Runtime;
use Sylius\Component\Core\Model\Order as BaseOrder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class ExtensionTest extends TestCase
{
    /**
     * @test
     */
    public function it_renders_the_source_of_an_order(): void
    {
        $order = new Order();

        $source = new Source();
        $source->setSource('google');

        $provider = $this->createMock(OrderAttributionProviderInterface::class);
        $provider->expects(self::once())->method('getSource')->with($order)->willReturn($source);

        self::assertSame('google', $this->render($provider, $order));
    }

    /**
     * @test
     */
    public function it_renders_nothing_for_an_order_without_a_source(): void
    {
        $provider = $this->createMock(OrderAttributionProviderInterface::class);
        $provider->method('getSource')->willReturn(null);

        self::assertSame('none', $this->render($provider, new Order()));
    }

    /**
     * @test
     */
    public function it_renders_nothing_for_an_order_that_does_not_implement_the_plugin_interface(): void
    {
        // A host application that hasn't added the plugin's interface to its Order entity
        $provider = $this->createMock(OrderAttributionProviderInterface::class);
        $provider->expects(self::never())->method('getSource');

        self::assertSame('none', $this->render($provider, new BaseOrder()));
    }

    private function render(OrderAttributionProviderInterface $provider, BaseOrder $order): string
    {
        $twig = new Environment(new ArrayLoader([
            // Like the admin template (order/attribution.html.twig)
            'template' => '{% set source = setono_sylius_conversion_attribution_last_non_direct(order) %}{{ source is null ? "none" : source.source }}',
        ]), ['strict_variables' => true]);
        $twig->addExtension(new Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            Runtime::class => static fn (): Runtime => new Runtime($provider),
        ]));

        return $twig->render('template', ['order' => $order]);
    }
}
