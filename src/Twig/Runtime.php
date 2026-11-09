<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Twig;

use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Setono\SyliusConversionAttributionPlugin\Repository\AttributionRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    public function __construct(private readonly AttributionRepositoryInterface $attributionRepository)
    {
    }

    public function getAttribution(OrderInterface $order): ?AttributionInterface
    {
        return $this->attributionRepository->findOneByOrder($order);
    }
}
