<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Twig;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Model\AttributionInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /** @var class-string<AttributionInterface> $attributionClass */
        private readonly string $attributionClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getAttribution(OrderInterface $order): ?AttributionInterface
    {
        return $this->getRepository($this->attributionClass)->findOneBy(['order' => $order]);
    }
}
