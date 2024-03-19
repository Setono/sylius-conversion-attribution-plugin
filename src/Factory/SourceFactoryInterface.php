<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Factory;

use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;

interface SourceFactoryInterface extends FactoryInterface
{
    public function createNew(): SourceInterface;

    public function createFromRequest(Request $request): SourceInterface;
}
