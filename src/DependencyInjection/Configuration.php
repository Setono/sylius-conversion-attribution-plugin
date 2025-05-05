<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\DependencyInjection;

use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_conversion_attribution');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @psalm-suppress UndefinedInterfaceMethod,MixedMethodCall,PossiblyNullReference */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('javascript')
                    ->info('If you want to enable the injection of javascript (through the tag bag bundle), set this to true. Default is true.')
                    ->canBeDisabled()
                ->end()
                ->arrayNode('query_parameters')
                    ->info('The query parameters to look for when trying to resolve source from query parameters')
                    ->arrayPrototype()
                        ->canBeDisabled()
                        ->children()
                            ->arrayNode('matches')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function (string $v): array { return [$v]; })
                                ->end()
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('source')
                                ->info('If not set, the value of the matched query parameter will be used')
                                ->defaultNull()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('medium')->defaultNull()->cannotBeEmpty()->end()
                            ->scalarNode('campaign')->defaultNull()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('session_timeout')
                    ->info('The number of seconds you consider a session lifetime. Default is 1800 seconds (30 minutes) which is used by Google Analytics and other analytics tools')
                    ->defaultValue(1800)
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        /** @psalm-suppress UndefinedInterfaceMethod, MixedMethodCall, PossiblyUndefinedMethod, PossiblyNullReference */
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('source')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                    ->scalarNode('model')->defaultValue(Source::class)->cannotBeEmpty()->end()
                                    ->scalarNode('repository')->cannotBeEmpty()->end()
        ;
    }
}
