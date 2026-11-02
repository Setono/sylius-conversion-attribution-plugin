<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use PHPUnit\Framework\TestCase;
use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusConversionAttributionPlugin\Matcher\CompositeSourceMatcher;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\SetonoSyliusConversionAttributionPlugin;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class SetonoSyliusConversionAttributionPluginTest extends TestCase
{
    /**
     * @test
     */
    public function it_supports_the_doctrine_orm_driver(): void
    {
        self::assertSame(
            [SyliusResourceBundle::DRIVER_DOCTRINE_ORM],
            (new SetonoSyliusConversionAttributionPlugin())->getSupportedDrivers(),
        );
    }

    /**
     * @test
     */
    public function it_registers_the_doctrine_mapping_of_its_models(): void
    {
        $container = new ContainerBuilder();
        (new SetonoSyliusConversionAttributionPlugin())->build($container);

        self::assertCount(1, self::passes($container, DoctrineOrmMappingsPass::class));
    }

    /**
     * @test
     */
    public function it_adds_the_tagged_source_matchers_to_the_composite_by_priority(): void
    {
        $container = new ContainerBuilder();
        (new SetonoSyliusConversionAttributionPlugin())->build($container);

        $container->register(CompositeSourceMatcher::class, CompositeSourceMatcher::class);
        $container->register('low', SourceMatcherInterface::class)
            ->addTag('setono_sylius_conversion_attribution.source_matcher', ['priority' => 100]);
        $container->register('high', SourceMatcherInterface::class)
            ->addTag('setono_sylius_conversion_attribution.source_matcher', ['priority' => 300]);
        $container->register('untagged', SourceMatcherInterface::class);

        $passes = self::passes($container, CompositeCompilerPass::class);
        self::assertCount(1, $passes);
        $passes[0]->process($container);

        self::assertEquals(
            [['add', [new Reference('high')]], ['add', [new Reference('low')]]],
            $container->getDefinition(CompositeSourceMatcher::class)->getMethodCalls(),
        );
    }

    /**
     * @template T of CompilerPassInterface
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private static function passes(ContainerBuilder $container, string $class): array
    {
        return array_values(array_filter(
            $container->getCompilerPassConfig()->getPasses(),
            static fn (CompilerPassInterface $pass): bool => $pass instanceof $class,
        ));
    }
}
