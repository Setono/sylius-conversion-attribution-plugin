<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\DependencyInjection;

use Setono\TagBagBundle\SetonoTagBagBundle;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

final class SetonoSyliusConversionAttributionExtension extends AbstractResourceExtension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{javascript: array{enabled: bool}, source_parameters: list<string>, session_timeout: int, resources: array} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_conversion_attribution.javascript.enabled', $config['javascript']['enabled']);
        $container->setParameter('setono_sylius_conversion_attribution.source_parameters', $config['source_parameters']);
        $container->setParameter('setono_sylius_conversion_attribution.session_timeout', $config['session_timeout']);

        $loader->load('services.xml');

        if ($config['javascript']['enabled']) {
            if (!self::bundleEnabled($container, SetonoTagBagBundle::class)) {
                throw new \LogicException('You need to install setono/tag-bag-bundle to use the javascript feature. See https://github.com/Setono/TagBagBundle');
            }

            $loader->load('services/conditional/event_subscriber.xml');
        }

        $this->registerResources(
            'setono_sylius_conversion_attribution',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );
    }

    /**
     * @param class-string<BundleInterface> $bundleClass
     */
    private static function bundleEnabled(ContainerBuilder $container, string $bundleClass): bool
    {
        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        $bundles = $container->getParameter('kernel.bundles');
        if (!is_array($bundles) || [] === $bundles) {
            return false;
        }

        $bundle = new $bundleClass();
        if (!$bundle instanceof BundleInterface) {
            throw new \LogicException(sprintf('The class %s is not a valid bundle class.', $bundleClass));
        }

        return isset($bundles[$bundle->getName()]);
    }
}
