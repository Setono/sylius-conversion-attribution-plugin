<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin;

use Setono\CompositeCompilerPass\CompositeCompilerPass;
use Setono\SyliusConversionAttributionPlugin\Matcher\CompositeSourceMatcher;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusConversionAttributionPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CompositeCompilerPass(
            CompositeSourceMatcher::class,
            'setono_sylius_conversion_attribution.source_matcher',
        ));
    }
}
