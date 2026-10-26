<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Factory;

use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class SourceFactory implements SourceFactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $decorated,
    ) {
    }

    public function createNew(): SourceInterface
    {
        /** @var SourceInterface|object $obj */
        $obj = $this->decorated->createNew();
        Assert::isInstanceOf($obj, SourceInterface::class);

        return $obj;
    }
}
