<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Factory;

use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class SourceFactory implements SourceFactoryInterface
{
    /**
     * @param FactoryInterface<SourceInterface> $decorated
     */
    public function __construct(
        private readonly FactoryInterface $decorated,
    ) {
    }

    public function createNew(): SourceInterface
    {
        $obj = $this->decorated->createNew();

        // The decorated factory creates the configured model class, so make sure it is a source
        Assert::isInstanceOf($obj, SourceInterface::class);

        return $obj;
    }
}
