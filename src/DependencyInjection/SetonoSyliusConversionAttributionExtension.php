<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusConversionAttributionExtension extends AbstractResourceExtension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{source_parameters: list<string>, session_timeout: integer, resources: array} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_conversion_attribution.source_parameters', $config['source_parameters']);
        $container->setParameter('setono_sylius_conversion_attribution.session_timeout', $config['session_timeout']);

        $loader->load('services.xml');

        $this->registerResources('setono_sylius_conversion_attribution', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);
    }
}
