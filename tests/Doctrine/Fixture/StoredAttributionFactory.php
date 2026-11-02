<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Doctrine\Fixture;

use Sylius\Resource\Factory\FactoryInterface;

/**
 * Creates attributions, like the resource bundle's factory for the attribution resource does in an application
 *
 * @implements FactoryInterface<StoredAttribution>
 */
final class StoredAttributionFactory implements FactoryInterface
{
    public function createNew(): StoredAttribution
    {
        return new StoredAttribution();
    }
}
