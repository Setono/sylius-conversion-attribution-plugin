<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Provider;

use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;

interface OrderAttributionProviderInterface
{
    public function getSource(OrderInterface $order): ?SourceInterface;
}
