<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Factory;

use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<SourceInterface>
 */
interface SourceFactoryInterface extends FactoryInterface
{
    public function createNew(): SourceInterface;
}
