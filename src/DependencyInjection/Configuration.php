<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\DependencyInjection;

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

        $rootNode
            ->children()
                ->scalarNode('option')
                    ->info('This is an example configuration option')
                    ->isRequired()
                    ->cannotBeEmpty()
        ;

        return $treeBuilder;
    }
}
