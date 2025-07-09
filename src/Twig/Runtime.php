<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Twig;

use Setono\SyliusConversionAttributionPlugin\Model\OrderInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Setono\SyliusConversionAttributionPlugin\Provider\OrderAttributionProviderInterface;
use Sylius\Component\Core\Model\OrderInterface as BaseOrderInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    public function __construct(private readonly OrderAttributionProviderInterface $orderAttributionProvider)
    {
    }

    public function getLastNonDirect(BaseOrderInterface $order): ?SourceInterface
    {
        if (!$order instanceof OrderInterface) {
            return null;
        }

        return $this->orderAttributionProvider->getSource($order);
    }
}
