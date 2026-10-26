<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\DependencyInjection;

use Setono\TagBagBundle\SetonoTagBagBundle;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

final class SetonoSyliusConversionAttributionExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{
         *     javascript: array{enabled: bool},
         *     query_parameters: array,
         *     session_timeout: int,
         *     resources: array
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_conversion_attribution.javascript.enabled', $config['javascript']['enabled']);
        $container->setParameter('setono_sylius_conversion_attribution.query_parameters', array_filter($config['query_parameters'], static function (array $queryParameter): bool {
            return !array_key_exists('enabled', $queryParameter) || true === $queryParameter['enabled'];
        }));
        $container->setParameter('setono_sylius_conversion_attribution.session_timeout', $config['session_timeout']);
        $container->setParameter('setono_sylius_conversion_attribution.referrers.cache.file', '%kernel.cache_dir%/referrers.php');
        $container->setParameter('setono_sylius_conversion_attribution.source.default', 'direct');

        $loader->load('services.xml');

        if ($config['javascript']['enabled']) {
            if (!self::bundleEnabled($container, SetonoTagBagBundle::class)) {
                throw new \LogicException('You need to install setono/tag-bag-bundle to use the javascript feature. See https://github.com/Setono/TagBagBundle');
            }

            $script = __DIR__ . '/../Resources/public/js/track.js';
            $scriptHash = hash_file('xxh128', $script);
            if (false === $scriptHash) {
                throw new \LogicException(sprintf('The tracking script "%s" could not be read', $script));
            }

            // The script's URL carries a hash of its contents (?v=...), so browsers and CDNs fetch the new
            // script after an upgrade. The hash is computed when the container is built, which happens on
            // every deploy in production. In development, Symfony only rebuilds the cached container when one
            // of the files it tracks changes; tracking the script means editing it bumps the hash too, instead
            // of the page serving the old one until the cache is cleared
            $container->addResource(new FileResource($script));
            $container->setParameter('setono_sylius_conversion_attribution.javascript.version', substr($scriptHash, 0, 12));

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

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('setono_sylius_conversion_attribution', [
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
        ]);

        $container->prependExtensionConfig('sylius_ui', [
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
        ]);
    }
}
