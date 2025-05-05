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
         * @var array{
         *     javascript: array{enabled: bool},
         *     query_parameters: array,
         *     session_timeout: int,
         *     resources: array
         * } $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $queryParameters = array_merge_recursive([
            'facebook' => [
                'matches' => ['fbclid'], 'source' => 'facebook.com', 'medium' => 'cpc',
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
        ], $config['query_parameters']);
        $queryParameters = array_filter($queryParameters, static function (array $queryParameter): bool {
            return !array_key_exists('enabled', $queryParameter) || true === $queryParameter['enabled'];
        });
        $queryParameters = array_map(
            /** @param array{matches: list<string>, source?: list<string|null>|string|null, medium?: list<string|null>|string|null, campaign?: list<string|null>|string|null, enabled?: bool} $queryParameter */
            static function (array $queryParameter): array {
                unset($queryParameter['enabled']);

                $queryParameter['matches'] = array_values(array_unique($queryParameter['matches']));

                // Because array_merge_recursive will merge associative keys by creating a new array, we will turn it back into a string
                $queryParameter['source'] = self::firstValue($queryParameter['source'] ?? null);
                $queryParameter['medium'] = self::firstValue($queryParameter['medium'] ?? null);
                $queryParameter['campaign'] = self::firstValue($queryParameter['campaign'] ?? null);

                return $queryParameter;
            },
            $queryParameters,
        );

        $container->setParameter('setono_sylius_conversion_attribution.javascript.enabled', $config['javascript']['enabled']);
        $container->setParameter('setono_sylius_conversion_attribution.query_parameters', $queryParameters);
        $container->setParameter('setono_sylius_conversion_attribution.session_timeout', $config['session_timeout']);
        $container->setParameter('setono_sylius_conversion_attribution.referrers.cache.file', '%kernel.cache_dir%/referrers.php');

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

    /**
     * @param string|list<string|null>|null $value
     */
    private static function firstValue(null|string|array $value): ?string
    {
        $array = (array) $value;
        foreach ($array as $item) {
            if (null !== $item) {
                return $item;
            }
        }

        return null;
    }
}
