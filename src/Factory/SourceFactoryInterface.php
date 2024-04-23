<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Factory;

use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface SourceFactoryInterface extends FactoryInterface
{
    public function createNew(): SourceInterface;

    public function createFromClientInformation(ClientInformation $clientInformation): SourceInterface;
}
